<?php
// modulo_productos.php - Gestión de productos CORREGIDO

require_once 'database.php';
require_once 'funciones.php';

// Verificar sesión
Funciones::verificarSesion();

$db = getDB();
$mensaje = '';
$error = '';

// Si hay acción GET para cargar datos (para AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];
    
    try {
        switch ($action) {
            case 'obtener_paquete':
                $id = $_GET['id'] ?? 0;
                $stmt = $db->prepare("SELECT * FROM paquetes WHERE id = ?");
                $stmt->execute([$id]);
                $paquete = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($paquete) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'data' => $paquete]);
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'Paquete no encontrado']);
                }
                exit();
                
            case 'obtener_paquete_completo':
                $id = $_GET['id'] ?? 0;
                
                // Obtener información del paquete
                $stmt = $db->prepare("SELECT p.*, 
                                             prov.nombre as proveedor_nombre, 
                                             cat.nombre as categoria_nombre
                                      FROM paquetes p
                                      JOIN proveedores prov ON p.proveedor_id = prov.id
                                      JOIN categorias cat ON p.categoria_id = cat.id
                                      WHERE p.id = ?");
                $stmt->execute([$id]);
                $paquete = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$paquete) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'Paquete no encontrado']);
                    exit();
                }
                
                // Obtener subpaquetes del paquete
                $stmt = $db->prepare("SELECT * FROM subpaquetes WHERE paquete_id = ? ORDER BY nombre_color");
                $stmt->execute([$id]);
                $subpaquetes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'paquete' => $paquete,
                    'subpaquetes' => $subpaquetes
                ]);
                exit();
                
            case 'obtener_subpaquete':
                $id = $_GET['id'] ?? 0;
                $stmt = $db->prepare("SELECT * FROM subpaquetes WHERE id = ?");
                $stmt->execute([$id]);
                $subpaquete = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($subpaquete) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'data' => $subpaquete]);
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'Subpaquete no encontrado']);
                }
                exit();
        }
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
}

// Procesar acciones CRUD POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'agregar_paquete':
                $codigo = Funciones::sanitizar($_POST['codigo']);
                $proveedor_id = $_POST['proveedor_id'];
                $categoria_id = $_POST['categoria_id'];
                $nombre = Funciones::sanitizar($_POST['nombre']);
                $descripcion = Funciones::sanitizar($_POST['descripcion']);
                $subpaquetes_por_paquete = $_POST['subpaquetes_por_paquete'];
                $costo = $_POST['costo'];
                $precio_venta_sugerido = $_POST['precio_venta_sugerido'];
                $fecha_ingreso = $_POST['fecha_ingreso'];
                $ubicacion = Funciones::sanitizar($_POST['ubicacion']);
                
                // Verificar código único
                $stmt = $db->prepare("SELECT id FROM paquetes WHERE codigo = ?");
                $stmt->execute([$codigo]);
                if ($stmt->fetch()) {
                    throw new Exception("El código ya existe");
                }
                
                $stmt = $db->prepare("INSERT INTO paquetes 
                                    (codigo, proveedor_id, categoria_id, nombre, descripcion,
                                     subpaquetes_por_paquete, costo, precio_venta_sugerido,
                                     fecha_ingreso, ubicacion)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $codigo, $proveedor_id, $categoria_id, $nombre, $descripcion,
                    $subpaquetes_por_paquete, $costo, $precio_venta_sugerido,
                    $fecha_ingreso, $ubicacion
                ]);
                
                $mensaje = "Paquete agregado exitosamente";
                break;
                
            case 'editar_paquete':
                $id = $_POST['id'];
                $nombre = Funciones::sanitizar($_POST['nombre']);
                $descripcion = Funciones::sanitizar($_POST['descripcion']);
                $costo = $_POST['costo'];
                $precio_venta_sugerido = $_POST['precio_venta_sugerido'];
                $ubicacion = Funciones::sanitizar($_POST['ubicacion']);
                
                $stmt = $db->prepare("UPDATE paquetes 
                                    SET nombre = ?, descripcion = ?, costo = ?,
                                        precio_venta_sugerido = ?, ubicacion = ?
                                    WHERE id = ?");
                $stmt->execute([
                    $nombre, $descripcion, $costo,
                    $precio_venta_sugerido, $ubicacion, $id
                ]);
                
                $mensaje = "Paquete actualizado exitosamente";
                break;
                
            case 'eliminar_paquete':
                $id = $_POST['id'];
                
                // Verificar si tiene subpaquetes con stock
                $stmt = $db->prepare("SELECT COUNT(*) as total FROM subpaquetes WHERE paquete_id = ? AND stock > 0");
                $stmt->execute([$id]);
                $result = $stmt->fetch();
                
                if ($result['total'] > 0) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => "No se puede eliminar el paquete porque tiene productos con stock"]);
                    exit();
                }
                
                $db->beginTransaction();
                
                try {
                    // Primero eliminar todos los subpaquetes (deben estar sin stock)
                    $stmt = $db->prepare("DELETE FROM subpaquetes WHERE paquete_id = ?");
                    $stmt->execute([$id]);
                    
                    // Luego eliminar el paquete
                    $stmt = $db->prepare("DELETE FROM paquetes WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    $db->commit();
                    
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => "Paquete eliminado exitosamente"]);
                    exit();
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => "Error al eliminar: " . $e->getMessage()]);
                    exit();
                }
                break;
                
            case 'agregar_subpaquete':
                $paquete_id = $_POST['paquete_id'];
                $codigo_color = Funciones::sanitizar($_POST['codigo_color']);
                $nombre_color = Funciones::sanitizar($_POST['nombre_color']);
                $precio_venta = $_POST['precio_venta'];
                $stock = $_POST['stock'];
                $min_stock = $_POST['min_stock'];
                $max_stock = $_POST['max_stock'];
                $ubicacion = Funciones::sanitizar($_POST['ubicacion']);
                
                // Verificar código único en el paquete
                $stmt = $db->prepare("SELECT id FROM subpaquetes WHERE paquete_id = ? AND codigo_color = ?");
                $stmt->execute([$paquete_id, $codigo_color]);
                if ($stmt->fetch()) {
                    throw new Exception("El código de color ya existe en este paquete");
                }
                
                $db->beginTransaction();
                
                try {
                    // Insertar subpaquete
                    $stmt = $db->prepare("INSERT INTO subpaquetes 
                                        (paquete_id, codigo_color, nombre_color, precio_venta,
                                         stock, min_stock, max_stock, ubicacion)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $stmt->execute([
                        $paquete_id, $codigo_color, $nombre_color, $precio_venta,
                        $stock, $min_stock, $max_stock, $ubicacion
                    ]);
                    
                    $subpaquete_id = $db->lastInsertId();
                    
                    // Actualizar contadores del paquete
                    $stmt = $db->prepare("UPDATE paquetes 
                                        SET cantidad_subpaquetes = COALESCE(cantidad_subpaquetes, 0) + 1
                                        WHERE id = ?");
                    $stmt->execute([$paquete_id]);
                    
                    // Registrar movimiento de stock SOLO SI HAY STOCK
                    if ($stock > 0) {
                        $stmt = $db->prepare("INSERT INTO movimientos_stock 
                                            (subpaquete_id, tipo, cantidad, stock_anterior, stock_nuevo,
                                             usuario_id, fecha_hora, observaciones)
                                            VALUES (?, 'ingreso', ?, 0, ?, ?, NOW(), ?)");
                        $stmt->execute([
                            $subpaquete_id, 
                            $stock, 
                            $stock,
                            $_SESSION['usuario_id'],
                            'Ingreso inicial'
                        ]);
                    }
                    
                    $db->commit();
                    $mensaje = "Subpaquete agregado exitosamente";
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    throw $e;
                }
                break;
                
            case 'editar_subpaquete':
                $id = $_POST['id'];
                $nombre_color = Funciones::sanitizar($_POST['nombre_color']);
                $precio_venta = $_POST['precio_venta'];
                $min_stock = $_POST['min_stock'];
                $max_stock = $_POST['max_stock'];
                $ubicacion = Funciones::sanitizar($_POST['ubicacion']);
                
                $stmt = $db->prepare("UPDATE subpaquetes 
                                    SET nombre_color = ?, precio_venta = ?,
                                        min_stock = ?, max_stock = ?, ubicacion = ?
                                    WHERE id = ?");
                $stmt->execute([
                    $nombre_color, $precio_venta,
                    $min_stock, $max_stock, $ubicacion, $id
                ]);
                
                $mensaje = "Subpaquete actualizado exitosamente";
                break;
                
            case 'eliminar_subpaquete':
                $id = $_POST['id'];
                
                // Verificar si tiene stock
                $stmt = $db->prepare("SELECT stock, paquete_id FROM subpaquetes WHERE id = ?");
                $stmt->execute([$id]);
                $subpaquete = $stmt->fetch();
                
                if (!$subpaquete) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => "Subpaquete no encontrado"]);
                    exit();
                }
                
                if ($subpaquete['stock'] > 0) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => "No se puede eliminar porque tiene stock disponible"]);
                    exit();
                }
                
                $db->beginTransaction();
                
                try {
                    // Eliminar subpaquete
                    $stmt = $db->prepare("DELETE FROM subpaquetes WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    // Actualizar contadores del paquete
                    $stmt = $db->prepare("UPDATE paquetes 
                                        SET cantidad_subpaquetes = cantidad_subpaquetes - 1 
                                        WHERE id = ?");
                    $stmt->execute([$subpaquete['paquete_id']]);
                    
                    $db->commit();
                    
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => "Subpaquete eliminado exitosamente"]);
                    exit();
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => "Error al eliminar: " . $e->getMessage()]);
                    exit();
                }
                break;
            case 'ajustar_stock':
                $id = $_POST['id'];
                $nuevo_stock = $_POST['stock'];
                $observaciones = Funciones::sanitizar($_POST['observaciones']);
                
                // Obtener stock actual y verificar que el subpaquete existe
                $stmt = $db->prepare("SELECT stock FROM subpaquetes WHERE id = ?");
                $stmt->execute([$id]);
                $resultado = $stmt->fetch();
                
                if (!$resultado) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => "Subpaquete no encontrado"]);
                    exit();
                }
                
                $stock_actual = $resultado['stock'];
                $diferencia = $nuevo_stock - $stock_actual;
                
                $db->beginTransaction();
                
                try {
                    // Actualizar stock
                    $stmt = $db->prepare("UPDATE subpaquetes SET stock = ? WHERE id = ?");
                    $stmt->execute([$nuevo_stock, $id]);
                    
                    // Registrar movimiento
                    $stmt = $db->prepare("INSERT INTO movimientos_stock 
                                        (subpaquete_id, tipo, cantidad, stock_anterior, stock_nuevo,
                                        usuario_id, fecha_hora, observaciones)
                                        VALUES (?, 'ajuste', ?, ?, ?, ?, NOW(), ?)");
                    $stmt->execute([
                        $id, 
                        $diferencia, 
                        $stock_actual, 
                        $nuevo_stock,
                        $_SESSION['usuario_id'], 
                        $observaciones
                    ]);
                    
                    $db->commit();
                    
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => "Stock ajustado exitosamente"]);
                    exit();
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => "Error al ajustar stock: " . $e->getMessage()]);
                    exit();
                }
                break;    
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Inicializar arrays para evitar errores
$proveedores = [];
$categorias = [];
$paquetes = [];
$subpaquetes = [];
$stock_bajo = [];

// Obtener datos para los select
try {
    // Proveedores
    $stmt = $db->query("SELECT id, nombre FROM proveedores ORDER BY nombre");
    $proveedores = $stmt->fetchAll();
    
    // Categorías
    $stmt = $db->query("SELECT c.*, p.nombre as proveedor_nombre 
                       FROM categorias c 
                       JOIN proveedores p ON c.proveedor_id = p.id 
                       ORDER BY p.nombre, c.nombre");
    $categorias = $stmt->fetchAll();
    
    // Paquetes
    $stmt = $db->query("SELECT p.*, 
                               prov.nombre as proveedor_nombre, 
                               cat.nombre as categoria_nombre
                       FROM paquetes p
                       JOIN proveedores prov ON p.proveedor_id = prov.id
                       JOIN categorias cat ON p.categoria_id = cat.id
                       ORDER BY p.fecha_ingreso DESC");
    $paquetes = $stmt->fetchAll();
    
    // Subpaquetes
    $stmt = $db->query("SELECT s.*, 
                               p.nombre as paquete_nombre, 
                               p.codigo as paquete_codigo,
                               p.id as paquete_id
                       FROM subpaquetes s
                       JOIN paquetes p ON s.paquete_id = p.id
                       ORDER BY p.nombre, s.nombre_color");
    $subpaquetes = $stmt->fetchAll();
    
    // Productos bajos en stock
    $stmt = $db->prepare("SELECT s.*, 
                                 p.nombre as paquete_nombre,
                                 p.codigo as paquete_codigo
                         FROM subpaquetes s 
                         JOIN paquetes p ON s.paquete_id = p.id 
                         WHERE s.stock <= s.min_stock 
                         ORDER BY s.stock ASC 
                         LIMIT 10");
    $stmt->execute();
    $stock_bajo = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Error cargando datos: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos - Sistema de Inventario</title>
    
    <?php include 'header.php'; ?>
    
    <style>
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
        
        .stats-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        
        .table-actions {
            white-space: nowrap;
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
        
        .search-container {
            position: relative;
            max-width: 300px;
        }
        
        .search-container i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .search-container input {
            padding-left: 3rem;
        }
        
        .color-preview {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-block;
            border: 1px solid #dee2e6;
            margin-right: 8px;
            vertical-align: middle;
        }
        
        .stock-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
            vertical-align: middle;
        }
        
        .stock-high { background-color: #28a745; }
        .stock-medium { background-color: #ffc107; }
        .stock-low { background-color: #dc3545; }
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
                        <i class="fas fa-boxes me-2"></i>Gestión de Productos
                    </h1>
                    <p class="text-muted">
                        Administre paquetes, subpaquetes y controle el inventario
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-success" onclick="mostrarModalPaquete('nuevo')">
                        <i class="fas fa-plus me-2"></i>Nuevo Paquete
                    </button>
                    <button class="btn btn-outline-success" onclick="mostrarModalSubpaquete('nuevo')">
                        <i class="fas fa-plus-circle me-2"></i>Nuevo Subpaquete
                    </button>
                </div>
            </div>
            
            <!-- Mostrar mensajes -->
            <?php if ($mensaje): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($mensaje); ?>
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
                                        Paquetes Activos
                                    </div>
                                    <div class="h5 mb-0 fw-bold">
                                        <?php echo count($paquetes); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-box stats-icon text-success"></i>
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
                                        Subpaquetes
                                    </div>
                                    <div class="h5 mb-0 fw-bold">
                                        <?php echo count($subpaquetes); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-box-open stats-icon text-info"></i>
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
                                        Stock Bajo
                                    </div>
                                    <div class="h5 mb-0 fw-bold">
                                        <?php echo count($stock_bajo); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-exclamation-triangle stats-icon text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stats-card border-start border-primary border-4">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs fw-bold text-primary mb-1">
                                        Valor Inventario
                                    </div>
                                    <div class="h5 mb-0 fw-bold">
                                        <?php
                                        $valor_total = 0;
                                        foreach ($subpaquetes as $subpaquete) {
                                            $valor_total += $subpaquete['precio_venta'] * $subpaquete['stock'];
                                        }
                                        echo Funciones::formatearMoneda($valor_total);
                                        ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-dollar-sign stats-icon text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pestañas -->
            <ul class="nav nav-tabs mb-3" id="productTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="paquetes-tab" data-bs-toggle="tab" 
                            data-bs-target="#paquetes" type="button" role="tab">
                        <i class="fas fa-box me-2"></i>Paquetes
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="subpaquetes-tab" data-bs-toggle="tab" 
                            data-bs-target="#subpaquetes" type="button" role="tab">
                        <i class="fas fa-box-open me-2"></i>Subpaquetes
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="stock-tab" data-bs-toggle="tab" 
                            data-bs-target="#stock" type="button" role="tab">
                        <i class="fas fa-chart-bar me-2"></i>Control Stock
                    </button>
                </li>
            </ul>
            
            <!-- Contenido de pestañas -->
            <div class="tab-content" id="productTabsContent">
                
                <!-- Pestaña Paquetes -->
                <div class="tab-pane fade show active" id="paquetes" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Lista de Paquetes</h5>
                        <div class="search-container">
                            <i class="fas fa-search"></i>
                            <input type="text" class="form-control" id="searchPaquetes" 
                                   placeholder="Buscar paquetes...">
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover" id="tablePaquetes">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Nombre</th>
                                    <th>Proveedor</th>
                                    <th>Categoría</th>
                                    <th>Subpaquetes</th>
                                    <th>Costo</th>
                                    <th>Precio Sugerido</th>
                                    <th>Fecha Ingreso</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paquetes as $paquete): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-light text-dark"><?php echo $paquete['codigo']; ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($paquete['nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($paquete['proveedor_nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($paquete['categoria_nombre']); ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo $paquete['cantidad_subpaquetes']; ?> / <?php echo $paquete['subpaquetes_por_paquete']; ?>
                                            </span>
                                        </td>
                                        <td class="fw-bold"><?php echo Funciones::formatearMoneda($paquete['costo']); ?></td>
                                        <td class="fw-bold text-success"><?php echo Funciones::formatearMoneda($paquete['precio_venta_sugerido']); ?></td>
                                        <td><?php echo Funciones::formatearFecha($paquete['fecha_ingreso']); ?></td>
                                        <td class="table-actions">
                                            <button class="btn btn-sm btn-outline-success" 
                                                    onclick="verDetallesPaquete(<?php echo $paquete['id']; ?>)"
                                                    title="Ver detalles">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="mostrarModalPaquete('editar', <?php echo $paquete['id']; ?>)"
                                                    title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="eliminarPaquete(<?php echo $paquete['id']; ?>)"
                                                    title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Pestaña Subpaquetes -->
                <div class="tab-pane fade" id="subpaquetes" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Lista de Subpaquetes</h5>
                        <div class="search-container">
                            <i class="fas fa-search"></i>
                            <input type="text" class="form-control" id="searchSubpaquetes" 
                                   placeholder="Buscar subpaquetes...">
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover" id="tableSubpaquetes">
                            <thead>
                                <tr>
                                    <th>Color</th>
                                    <th>Código</th>
                                    <th>Paquete</th>
                                    <th>Precio Venta</th>
                                    <th>Stock</th>
                                    <th>Mínimo</th>
                                    <th>Máximo</th>
                                    <th>Ubicación</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subpaquetes as $subpaquete): 
                                    $stock_porcentaje = ($subpaquete['stock'] / $subpaquete['max_stock']) * 100;
                                    $stock_clase = $stock_porcentaje > 50 ? 'stock-high' : 
                                                  ($stock_porcentaje > 25 ? 'stock-medium' : 'stock-low');
                                ?>
                                    <tr>
                                        <td>
                                            <div class="color-preview" 
                                                 style="background: linear-gradient(135deg, #<?php echo substr(md5($subpaquete['nombre_color']), 0, 6); ?>, #<?php echo substr(md5($subpaquete['codigo_color']), 0, 6); ?>)">
                                            </div>
                                            <?php echo htmlspecialchars($subpaquete['nombre_color']); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark"><?php echo $subpaquete['codigo_color']; ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($subpaquete['paquete_nombre']); ?></td>
                                        <td class="fw-bold text-success"><?php echo Funciones::formatearMoneda($subpaquete['precio_venta']); ?></td>
                                        <td>
                                            <span class="stock-indicator <?php echo $stock_clase; ?>"></span>
                                            <span class="fw-bold <?php echo $subpaquete['stock'] <= $subpaquete['min_stock'] ? 'text-danger' : ''; ?>">
                                                <?php echo $subpaquete['stock']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $subpaquete['min_stock']; ?></td>
                                        <td><?php echo $subpaquete['max_stock']; ?></td>
                                        <td><?php echo htmlspecialchars($subpaquete['ubicacion']); ?></td>
                                        <td class="table-actions">
                                            <button class="btn btn-sm btn-outline-warning" 
                                                    onclick="ajustarStock(<?php echo $subpaquete['id']; ?>)" 
                                                    title="Ajustar stock">
                                                <i class="fas fa-sliders-h"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="mostrarModalSubpaquete('editar', <?php echo $subpaquete['id']; ?>)"
                                                    title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="eliminarSubpaquete(<?php echo $subpaquete['id']; ?>)"
                                                    title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Pestaña Control Stock -->
                <div class="tab-pane fade" id="stock" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header bg-warning text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        Productos con Stock Bajo
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($stock_bajo)): ?>
                                        <div class="text-center py-3 text-muted">
                                            <i class="fas fa-check-circle fa-2x mb-2"></i>
                                            <p>Todos los productos tienen stock suficiente</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Producto</th>
                                                        <th>Paquete</th>
                                                        <th>Stock Actual</th>
                                                        <th>Mínimo</th>
                                                        <th>Diferencia</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
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
                                                                <span class="badge bg-warning">
                                                                    <?php echo $producto['min_stock'] - $producto['stock']; ?>
                                                                </span>
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
                                        Estadísticas de Stock
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="stockChart" height="250"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-download me-2"></i>
                                Reposición Rápida
                            </h5>
                        </div>
                        <div class="card-body">
                            <form id="formReposicionRapida" class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Seleccionar Producto</label>
                                    <select class="form-select" id="selectReponerProducto" required>
                                        <option value="">Seleccione un producto...</option>
                                        <?php foreach ($subpaquetes as $producto): ?>
                                            <option value="<?php echo $producto['id']; ?>"
                                                    data-stock="<?php echo $producto['stock']; ?>"
                                                    data-max="<?php echo $producto['max_stock']; ?>">
                                                <?php echo htmlspecialchars($producto['paquete_nombre'] . ' - ' . $producto['nombre_color']); ?>
                                                (Stock: <?php echo $producto['stock']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Cantidad a Reponer</label>
                                    <input type="number" class="form-control" id="cantidadReponer" 
                                           min="1" max="1000" value="10" required>
                                    <small class="text-muted" id="stockInfo"></small>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Observación</label>
                                    <input type="text" class="form-control" id="observacionReposicion"
                                           placeholder="Motivo de reposición...">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-success w-100" onclick="reponerStock()">
                                        <i class="fas fa-plus me-2"></i>Reponer
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para paquete -->
    <div class="modal fade" id="modalPaquete" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="modalPaqueteTitle">
                        <i class="fas fa-box me-2"></i>Nuevo Paquete
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="formPaquete" method="POST">
                    <input type="hidden" name="action" value="agregar_paquete">
                    <input type="hidden" name="id" id="paqueteId">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Código *</label>
                                <input type="text" class="form-control" name="codigo" id="paqueteCodigo"
                                       title="Formato: ABC-123" required>
                                <small class="text-muted">Formato: PAQ-001</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Proveedor *</label>
                                <select class="form-select" name="proveedor_id" id="paqueteProveedor" required>
                                    <option value="">Seleccionar...</option>
                                    <?php foreach ($proveedores as $proveedor): ?>
                                        <option value="<?php echo $proveedor['id']; ?>">
                                            <?php echo htmlspecialchars($proveedor['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Categoría *</label>
                                <select class="form-select" name="categoria_id" id="paqueteCategoria" required>
                                    <option value="">Seleccionar...</option>
                                    <?php foreach ($categorias as $categoria): ?>
                                        <option value="<?php echo $categoria['id']; ?>">
                                            <?php echo htmlspecialchars($categoria['proveedor_nombre'] . ' - ' . $categoria['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre *</label>
                                <input type="text" class="form-control" name="nombre" id="paqueteNombre" required>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Descripción</label>
                                <textarea class="form-control" name="descripcion" id="paqueteDescripcion" rows="2"></textarea>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Subpaquetes por Paquete</label>
                                <input type="number" class="form-control" name="subpaquetes_por_paquete" 
                                       id="paqueteSubpaquetes" min="1" max="100" value="10" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Costo de Compra</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" name="costo" 
                                           id="paqueteCosto" step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Precio Sugerido</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" name="precio_venta_sugerido" 
                                           id="paquetePrecio" step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fecha de Ingreso</label>
                                <input type="date" class="form-control" name="fecha_ingreso" 
                                       id="paqueteFechaIngreso" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ubicación</label>
                                <input type="text" class="form-control" name="ubicacion" 
                                       id="paqueteUbicacion" placeholder="Ej: Estante A1">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-success" id="btnGuardarPaquete">
                            Guardar Paquete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal para subpaquete -->
    <div class="modal fade" id="modalSubpaquete" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="modalSubpaqueteTitle">
                        <i class="fas fa-box-open me-2"></i>Nuevo Subpaquete
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="formSubpaquete" method="POST">
                    <input type="hidden" name="action" value="agregar_subpaquete">
                    <input type="hidden" name="id" id="subpaqueteId">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label">Paquete *</label>
                                <select class="form-select" name="paquete_id" id="subpaquetePaquete" required>
                                    <option value="">Seleccionar...</option>
                                    <?php foreach ($paquetes as $paquete): ?>
                                        <option value="<?php echo $paquete['id']; ?>">
                                            <?php echo htmlspecialchars($paquete['codigo'] . ' - ' . $paquete['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Código Color *</label>
                                <input type="text" class="form-control" name="codigo_color" id="subpaqueteCodigo" required>
                                <small class="text-muted">Ej: ROJO-001</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre Color *</label>
                                <input type="text" class="form-control" name="nombre_color" id="subpaqueteNombre" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Precio de Venta</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" name="precio_venta" 
                                           id="subpaquetePrecio" step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Stock Inicial</label>
                                <input type="number" class="form-control" name="stock" 
                                       id="subpaqueteStock" min="0" max="1000" value="0" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Stock Mínimo</label>
                                <input type="number" class="form-control" name="min_stock" 
                                       id="subpaqueteMin" min="1" max="100" value="5" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Stock Máximo</label>
                                <input type="number" class="form-control" name="max_stock" 
                                       id="subpaqueteMax" min="10" max="1000" value="100" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Ubicación</label>
                                <input type="text" class="form-control" name="ubicacion" 
                                       id="subpaqueteUbicacion" placeholder="Ej: A1-01">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-success" id="btnGuardarSubpaquete">
                            Guardar Subpaquete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal para ajustar stock -->
    <div class="modal fade" id="modalAjustarStock" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-sliders-h me-2"></i>Ajustar Stock
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="formAjustarStock" method="POST">
                    <input type="hidden" name="action" value="ajustar_stock">
                    <input type="hidden" name="id" id="ajustar_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Producto</label>
                            <input type="text" class="form-control" id="ajustar_nombre" readonly>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Stock Actual</label>
                                <input type="number" class="form-control" id="ajustar_stock_actual" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Stock Mínimo</label>
                                <input type="number" class="form-control" id="ajustar_stock_min" readonly>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nuevo Stock *</label>
                            <input type="number" class="form-control" name="stock" 
                                id="ajustar_nuevo_stock" min="0" max="1000" required>
                            <small class="text-muted">Ingrese la nueva cantidad de stock</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Observación *</label>
                            <textarea class="form-control" name="observaciones" id="ajustar_observacion" 
                                    rows="3" placeholder="Motivo del ajuste de stock..." required></textarea>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <small>El ajuste de stock registrará un movimiento en el historial.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-warning" id="btnAjustarStock">
                            <i class="fas fa-save me-2"></i>Ajustar Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal para ver detalles de paquete -->
    <div class="modal fade" id="modalDetallesPaquete" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-info-circle me-2"></i>Detalles del Paquete
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="mb-3">Información General</h5>
                            <table class="table table-bordered">
                                <tr>
                                    <th width="40%">Código:</th>
                                    <td id="detalleCodigo"></td>
                                </tr>
                                <tr>
                                    <th>Nombre:</th>
                                    <td id="detalleNombre"></td>
                                </tr>
                                <tr>
                                    <th>Proveedor:</th>
                                    <td id="detalleProveedor"></td>
                                </tr>
                                <tr>
                                    <th>Categoría:</th>
                                    <td id="detalleCategoria"></td>
                                </tr>
                                <tr>
                                    <th>Descripción:</th>
                                    <td id="detalleDescripcion"></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h5 class="mb-3">Información Financiera</h5>
                            <table class="table table-bordered">
                                <tr>
                                    <th width="40%">Costo:</th>
                                    <td id="detalleCosto" class="fw-bold"></td>
                                </tr>
                                <tr>
                                    <th>Precio Sugerido:</th>
                                    <td id="detallePrecioSugerido" class="fw-bold text-success"></td>
                                </tr>
                                <tr>
                                    <th>Subpaquetes:</th>
                                    <td id="detalleSubpaquetes" class="fw-bold"></td>
                                </tr>
                                <tr>
                                    <th>Fecha Ingreso:</th>
                                    <td id="detalleFechaIngreso"></td>
                                </tr>
                                <tr>
                                    <th>Ubicación:</th>
                                    <td id="detalleUbicacion"></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <h5 class="mb-3">Subpaquetes Asociados</h5>
                        <div class="table-responsive">
                            <table class="table table-sm" id="tablaSubpaquetesDetalle">
                                <thead>
                                    <tr>
                                        <th>Color</th>
                                        <th>Código</th>
                                        <th>Precio</th>
                                        <th>Stock</th>
                                        <th>Mínimo</th>
                                        <th>Máximo</th>
                                        <th>Ubicación</th>
                                    </tr>
                                </thead>
                                <tbody id="cuerpoSubpaquetesDetalle">
                                    <!-- Cargado dinámicamente -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        // Inicializar DataTables
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar gráfico
            inicializarGraficoStock();
            
            // Configurar búsqueda en tablas
            document.getElementById('searchPaquetes').addEventListener('input', function() {
                filtrarTabla('tablePaquetes', this.value);
            });
            
            document.getElementById('searchSubpaquetes').addEventListener('input', function() {
                filtrarTabla('tableSubpaquetes', this.value);
            });
            
            // Configurar eventos de formularios
            document.getElementById('selectReponerProducto').addEventListener('change', function() {
                const option = this.options[this.selectedIndex];
                const stockActual = option.dataset.stock || 0;
                const stockMaximo = option.dataset.max || 100;
                
                document.getElementById('stockInfo').textContent = 
                    `Actual: ${stockActual}, Máximo: ${stockMaximo}`;
                
                document.getElementById('cantidadReponer').max = stockMaximo - stockActual;
            });
        });
        
        // Función para filtrar tabla
        function filtrarTabla(tableId, searchText) {
            const table = document.getElementById(tableId);
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            const search = searchText.toLowerCase();
            
            for (let i = 0; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < cells.length; j++) {
                    if (cells[j].textContent.toLowerCase().includes(search)) {
                        found = true;
                        break;
                    }
                }
                
                rows[i].style.display = found ? '' : 'none';
            }
        }
        
        // Gráfico de stock
        function inicializarGraficoStock() {
            const ctx = document.getElementById('stockChart').getContext('2d');
            
            // Datos del gráfico
            const bajo = <?php echo count($stock_bajo); ?>;
            const medio = <?php echo count(array_filter($subpaquetes, function($p) { 
                return $p['stock'] > $p['min_stock'] && $p['stock'] <= ($p['min_stock'] * 2); 
            })); ?>;
            const alto = <?php echo count(array_filter($subpaquetes, function($p) { 
                return $p['stock'] > ($p['min_stock'] * 2); 
            })); ?>;
            
            const chart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Stock Bajo', 'Stock Medio', 'Stock Alto'],
                    datasets: [{
                        data: [bajo, medio, alto],
                        backgroundColor: [
                            '#dc3545',
                            '#ffc107',
                            '#28a745'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} productos (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Funciones CRUD para paquetes
        function mostrarModalPaquete(accion, id = null) {
            const modal = new bootstrap.Modal(document.getElementById('modalPaquete'));
            const form = document.getElementById('formPaquete');
            const title = document.getElementById('modalPaqueteTitle');
            const btn = document.getElementById('btnGuardarPaquete');
            
            if (accion === 'nuevo') {
                // Configurar para nuevo paquete
                title.innerHTML = '<i class="fas fa-box me-2"></i>Nuevo Paquete';
                btn.textContent = 'Guardar Paquete';
                form.reset();
                form.querySelector('input[name="action"]').value = 'agregar_paquete';
                document.getElementById('paqueteId').value = '';
                document.getElementById('paqueteCodigo').readOnly = false;
                document.getElementById('paqueteFechaIngreso').value = '<?php echo date("Y-m-d"); ?>';
                
            } else if (accion === 'editar' && id) {
                // Cargar datos del paquete mediante AJAX
                fetch(`modulo_productos.php?action=obtener_paquete&id=${id}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Error en la respuesta del servidor');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            const paquete = data.data;
                            title.innerHTML = '<i class="fas fa-edit me-2"></i>Editar Paquete';
                            btn.textContent = 'Actualizar Paquete';
                            form.querySelector('input[name="action"]').value = 'editar_paquete';
                            document.getElementById('paqueteId').value = id;
                            document.getElementById('paqueteCodigo').value = paquete.codigo;
                            document.getElementById('paqueteCodigo').readOnly = true;
                            document.getElementById('paqueteProveedor').value = paquete.proveedor_id;
                            document.getElementById('paqueteCategoria').value = paquete.categoria_id;
                            document.getElementById('paqueteNombre').value = paquete.nombre;
                            document.getElementById('paqueteDescripcion').value = paquete.descripcion || '';
                            document.getElementById('paqueteSubpaquetes').value = paquete.subpaquetes_por_paquete;
                            document.getElementById('paqueteCosto').value = paquete.costo;
                            document.getElementById('paquetePrecio').value = paquete.precio_venta_sugerido;
                            document.getElementById('paqueteFechaIngreso').value = paquete.fecha_ingreso;
                            document.getElementById('paqueteUbicacion').value = paquete.ubicacion || '';
                            
                            modal.show();
                        } else {
                            alert(data.error || 'Error al cargar datos del paquete');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error al cargar datos del paquete. Por favor, recargue la página.');
                    });
                return; // Salir para esperar la carga AJAX
            }
            
            modal.show();
        }
        
        function verDetallesPaquete(id) {
            // Cargar datos completos del paquete mediante AJAX
            fetch(`modulo_productos.php?action=obtener_paquete_completo&id=${id}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error en la respuesta del servidor');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const paquete = data.paquete;
                        const subpaquetes = data.subpaquetes;
                        
                        // Llenar información general
                        document.getElementById('detalleCodigo').textContent = paquete.codigo;
                        document.getElementById('detalleNombre').textContent = paquete.nombre;
                        document.getElementById('detalleProveedor').textContent = paquete.proveedor_nombre;
                        document.getElementById('detalleCategoria').textContent = paquete.categoria_nombre;
                        document.getElementById('detalleDescripcion').textContent = paquete.descripcion || 'Sin descripción';
                        document.getElementById('detalleCosto').textContent = '$' + parseFloat(paquete.costo).toFixed(2);
                        document.getElementById('detallePrecioSugerido').textContent = '$' + parseFloat(paquete.precio_venta_sugerido).toFixed(2);
                        document.getElementById('detalleSubpaquetes').textContent = paquete.cantidad_subpaquetes + ' / ' + paquete.subpaquetes_por_paquete;
                        document.getElementById('detalleFechaIngreso').textContent = new Date(paquete.fecha_ingreso).toLocaleDateString('es-ES');
                        document.getElementById('detalleUbicacion').textContent = paquete.ubicacion || 'No especificada';
                        
                        // Llenar tabla de subpaquetes
                        const tbody = document.getElementById('cuerpoSubpaquetesDetalle');
                        tbody.innerHTML = '';
                        
                        if (subpaquetes.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="7" class="text-center">No hay subpaquetes asociados</td></tr>';
                        } else {
                            subpaquetes.forEach(subpaquete => {
                                const stockClass = subpaquete.stock <= subpaquete.min_stock ? 'text-danger fw-bold' : 
                                                   subpaquete.stock <= subpaquete.min_stock * 2 ? 'text-warning' : 'text-success';
                                
                                const colorHex = '#' + (subpaquete.id % 16777215).toString(16).padStart(6, '0');
                                
                                const row = document.createElement('tr');
                                row.innerHTML = `
                                    <td>
                                        <div class="color-preview" style="background: ${colorHex}"></div>
                                        ${subpaquete.nombre_color}
                                    </td>
                                    <td>${subpaquete.codigo_color}</td>
                                    <td>$${parseFloat(subpaquete.precio_venta).toFixed(2)}</td>
                                    <td class="${stockClass}">${subpaquete.stock}</td>
                                    <td>${subpaquete.min_stock}</td>
                                    <td>${subpaquete.max_stock}</td>
                                    <td>${subpaquete.ubicacion || '-'}</td>
                                `;
                                tbody.appendChild(row);
                            });
                        }
                        
                        // Mostrar modal
                        const modal = new bootstrap.Modal(document.getElementById('modalDetallesPaquete'));
                        modal.show();
                    } else {
                        alert(data.error || 'Error al cargar detalles del paquete');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cargar detalles del paquete. Por favor, intente nuevamente.');
                });
        }
        
        function eliminarPaquete(id) {
            if (confirm('¿Está seguro de eliminar este paquete?\n\nEsta acción eliminará TODOS los subpaquetes asociados.\nEsta acción no se puede deshacer.')) {
                const formData = new FormData();
                formData.append('action', 'eliminar_paquete');
                formData.append('id', id);
                
                fetch('modulo_productos.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al eliminar el paquete: ' + error.message);
                });
            }
        }
        
        // Funciones CRUD para subpaquetes
        function mostrarModalSubpaquete(accion, id = null) {
            const modal = new bootstrap.Modal(document.getElementById('modalSubpaquete'));
            const form = document.getElementById('formSubpaquete');
            const title = document.getElementById('modalSubpaqueteTitle');
            const btn = document.getElementById('btnGuardarSubpaquete');
            
            if (accion === 'nuevo') {
                // Configurar para nuevo subpaquete
                title.innerHTML = '<i class="fas fa-box-open me-2"></i>Nuevo Subpaquete';
                btn.textContent = 'Guardar Subpaquete';
                form.reset();
                form.querySelector('input[name="action"]').value = 'agregar_subpaquete';
                document.getElementById('subpaqueteId').value = '';
                document.getElementById('subpaqueteCodigo').readOnly = false;
                document.getElementById('subpaqueteStock').value = '0';
                document.getElementById('subpaqueteMin').value = '5';
                document.getElementById('subpaqueteMax').value = '100';
                
                modal.show();
                
            } else if (accion === 'editar' && id) {
                // Cargar datos del subpaquete mediante AJAX
                fetch(`modulo_productos.php?action=obtener_subpaquete&id=${id}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Error en la respuesta del servidor');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            const subpaquete = data.data;
                            
                            title.innerHTML = '<i class="fas fa-edit me-2"></i>Editar Subpaquete';
                            btn.textContent = 'Actualizar Subpaquete';
                            form.querySelector('input[name="action"]').value = 'editar_subpaquete';
                            document.getElementById('subpaqueteId').value = id;
                            document.getElementById('subpaquetePaquete').value = subpaquete.paquete_id;
                            document.getElementById('subpaquetePaquete').disabled = true;
                            document.getElementById('subpaqueteCodigo').value = subpaquete.codigo_color;
                            document.getElementById('subpaqueteCodigo').readOnly = true;
                            document.getElementById('subpaqueteNombre').value = subpaquete.nombre_color;
                            document.getElementById('subpaquetePrecio').value = subpaquete.precio_venta;
                            document.getElementById('subpaqueteMin').value = subpaquete.min_stock;
                            document.getElementById('subpaqueteMax').value = subpaquete.max_stock;
                            document.getElementById('subpaqueteUbicacion').value = subpaquete.ubicacion || '';
                            document.getElementById('subpaqueteStock').value = subpaquete.stock;
                            
                            modal.show();
                        } else {
                            alert(data.error || 'Error al cargar datos del subpaquete');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error al cargar datos del subpaquete');
                    });
            }
        }
        
        function eliminarSubpaquete(id) {
            if (confirm('¿Está seguro de eliminar este subpaquete?\n\nEsta acción no se puede deshacer.')) {        
            const formData = new FormData();
            formData.append('action', 'eliminar_subpaquete');
            formData.append('id', id);
            
            fetch('modulo_productos.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al eliminar el subpaquete: ' + error.message);
            });
            }
        }
        
                // Funciones para ajustar stock - VERSIÓN CORREGIDA
        function ajustarStock(id) {
            console.log('Intentando ajustar stock para ID:', id); // Para depurar
            
            // Primero intentar obtener datos directamente de la fila de la tabla
            const rows = document.querySelectorAll('#tableSubpaquetes tbody tr');
            let encontrado = false;
            
            for (let row of rows) {
                // Buscar el botón que tiene el onclick con este ID
                const boton = row.querySelector(`button[onclick*="ajustarStock(${id})"]`);
                
                if (boton) {
                    encontrado = true;
                    const cells = row.querySelectorAll('td');
                    
                    if (cells.length >= 8) {
                        // Obtener datos de la fila - corrección en los índices
                        const nombreColor = cells[0].querySelector('.color-preview')?.nextSibling?.textContent.trim() || 
                                        cells[0].textContent.replace(/[\s\S]*color-preview[\s\S]*/, '').trim();
                        const stockActual = cells[4].querySelector('span.fw-bold')?.textContent.trim() || 
                                        cells[4].textContent.trim();
                        const stockMin = cells[5].textContent.trim();
                        const stockMax = cells[6].textContent.trim();
                        
                        console.log('Datos encontrados en tabla:', {
                            nombreColor,
                            stockActual,
                            stockMin,
                            stockMax
                        });
                        
                        // Llenar el formulario
                        document.getElementById('ajustar_id').value = id;
                        document.getElementById('ajustar_nombre').value = nombreColor;
                        document.getElementById('ajustar_stock_actual').value = stockActual;
                        document.getElementById('ajustar_stock_min').value = stockMin;
                        document.getElementById('ajustar_nuevo_stock').value = stockActual;
                        document.getElementById('ajustar_nuevo_stock').min = 0;
                        document.getElementById('ajustar_nuevo_stock').max = stockMax;
                        document.getElementById('ajustar_observacion').value = '';
                        
                        // Mostrar el modal
                        const modal = new bootstrap.Modal(document.getElementById('modalAjustarStock'));
                        modal.show();
                        return;
                    }
                }
            }
            
            // Si no se encontró en la tabla, cargar por AJAX
            if (!encontrado) {
                console.log('No encontrado en tabla, cargando por AJAX...');
                cargarDatosSubpaqueteParaAjuste(id);
            } else {
                console.error('Fila encontrada pero no se pudieron obtener los datos');
                // Intentar cargar por AJAX como fallback
                cargarDatosSubpaqueteParaAjuste(id);
            }
        }

        function cargarDatosSubpaqueteParaAjuste(id) {
            console.log('Cargando datos por AJAX para ID:', id);
            
            fetch(`modulo_productos.php?action=obtener_subpaquete&id=${id}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error en la respuesta del servidor');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Respuesta AJAX:', data);
                    if (data.success) {
                        const subpaquete = data.data;
                        
                        document.getElementById('ajustar_id').value = id;
                        document.getElementById('ajustar_nombre').value = subpaquete.nombre_color;
                        document.getElementById('ajustar_stock_actual').value = subpaquete.stock;
                        document.getElementById('ajustar_stock_min').value = subpaquete.min_stock;
                        document.getElementById('ajustar_nuevo_stock').value = subpaquete.stock;
                        document.getElementById('ajustar_nuevo_stock').min = 0;
                        document.getElementById('ajustar_nuevo_stock').max = subpaquete.max_stock;
                        document.getElementById('ajustar_observacion').value = '';
                        
                        const modal = new bootstrap.Modal(document.getElementById('modalAjustarStock'));
                        modal.show();
                    } else {
                        alert('Error al cargar datos del producto: ' + (data.error || 'Error desconocido'));
                    }
                })
                .catch(error => {
                    console.error('Error en AJAX:', error);
                    alert('Error al cargar datos del producto. Por favor, recargue la página.');
                });
        }

        // Manejar el envío del formulario de ajustar stock - VERSIÓN MEJORADA
        document.getElementById('formAjustarStock').addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const form = e.target;
            const formData = new FormData(form);
            const btn = form.querySelector('#btnAjustarStock');
            const originalBtnText = btn.innerHTML;
            
            // Validación
            const nuevoStock = document.getElementById('ajustar_nuevo_stock').value;
            const stockActual = document.getElementById('ajustar_stock_actual').value;
            const observacion = document.getElementById('ajustar_observacion').value;
            
            if (!observacion.trim()) {
                alert('Por favor ingrese una observación para el ajuste');
                document.getElementById('ajustar_observacion').focus();
                return false;
            }
    
        // Confirmar si es un ajuste significativo
            const diferencia = Math.abs(parseInt(nuevoStock) - parseInt(stockActual));
            if (diferencia > 10) {
                if (!confirm(`¿Está seguro de ajustar el stock en ${diferencia} unidades?`)) {
                    return false;
                }
            }
            
            // Cambiar texto del botón y deshabilitarlo
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Procesando...';
            btn.disabled = true;
            
            // Enviar por AJAX
            fetch('modulo_productos.php', {
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
                console.log('Respuesta del servidor:', data);
                if (data.success) {
                    // Mostrar mensaje de éxito
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: data.message || 'Stock ajustado exitosamente',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    // Cerrar el modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('modalAjustarStock'));
                    modal.hide();
                    
                    // Recargar la página después de un breve tiempo
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                    
                } else {
                    // Restaurar botón
                    btn.innerHTML = originalBtnText;
                    btn.disabled = false;
                    
                    // Mostrar error
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Error desconocido al ajustar stock'
                    });
                }
            })
            .catch(error => {
                // Restaurar botón
                btn.innerHTML = originalBtnText;
                btn.disabled = false;
                
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexión',
                    text: 'Error al ajustar stock: ' + error.message
                });
            });
            
            return false;
        });
        
        function reponerStock() {
            const productoId = document.getElementById('selectReponerProducto').value;
            const cantidad = document.getElementById('cantidadReponer').value;
            const observacion = document.getElementById('observacionReposicion').value;
            
            if (!productoId) {
                alert('Seleccione un producto');
                document.getElementById('selectReponerProducto').focus();
                return;
            }
            
            if (!cantidad || cantidad <= 0) {
                alert('Ingrese una cantidad válida');
                document.getElementById('cantidadReponer').focus();
                return;
            }
            
            const option = document.getElementById('selectReponerProducto').options[document.getElementById('selectReponerProducto').selectedIndex];
            const stockActual = parseInt(option.dataset.stock) || 0;
            const stockMaximo = parseInt(option.dataset.max) || 100;
            const productoNombre = option.text.split('(')[0].trim();
            const nuevoStock = stockActual + parseInt(cantidad);
            
            // Validar que no exceda el máximo
            if (nuevoStock > stockMaximo) {
                alert(`No puede reponer ${cantidad} unidades porque excedería el stock máximo (${stockMaximo}).\nStock actual: ${stockActual}\nMáximo permitido: ${stockMaximo}`);
                return;
            }
            
            if (confirm(`¿Reponer ${cantidad} unidades de "${productoNombre}"?\n\nStock actual: ${stockActual}\nNuevo stock: ${nuevoStock}`)) {
                
                const formData = new FormData();
                formData.append('action', 'ajustar_stock');
                formData.append('id', productoId);
                formData.append('stock', nuevoStock);
                formData.append('observaciones', `Reposición rápida: ${observacion || 'Reposición de stock'}`);
                
                // Mostrar indicador de carga
                const btn = document.querySelector('#formReposicionRapida button[type="button"]');
                const originalBtnText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Reponiendo...';
                btn.disabled = true;
                
                fetch('modulo_productos.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message || 'Stock repuesto exitosamente');
                        location.reload();
                    } else {
                        // Restaurar botón
                        btn.innerHTML = originalBtnText;
                        btn.disabled = false;
                        
                        alert('Error: ' + (data.message || 'Error al reponer stock'));
                    }
                })
                .catch(error => {
                    // Restaurar botón
                    btn.innerHTML = originalBtnText;
                    btn.disabled = false;
                    
                    console.error('Error:', error);
                    alert('Error de conexión al reponer stock: ' + error.message);
                });
            }
        }
        
        // Validación de formulario de paquete
        document.getElementById('formPaquete').addEventListener('submit', function(e) {
            const codigo = document.getElementById('paqueteCodigo').value;
            const nombre = document.getElementById('paqueteNombre').value;
            const proveedor = document.getElementById('paqueteProveedor').value;
            const categoria = document.getElementById('paqueteCategoria').value;
            const costo = document.getElementById('paqueteCosto').value;
            const precio = document.getElementById('paquetePrecio').value;
            
            if (!codigo.trim()) {
                alert('El código es obligatorio');
                e.preventDefault();
                return;
            }
            
            if (!nombre.trim()) {
                alert('El nombre es obligatorio');
                e.preventDefault();
                return;
            }
            
            if (!proveedor) {
                alert('Seleccione un proveedor');
                e.preventDefault();
                return;
            }
            
            if (!categoria) {
                alert('Seleccione una categoría');
                e.preventDefault();
                return;
            }
            
            if (!costo || parseFloat(costo) <= 0) {
                alert('Ingrese un costo válido');
                e.preventDefault();
                return;
            }
            
            if (!precio || parseFloat(precio) <= 0) {
                alert('Ingrese un precio sugerido válido');
                e.preventDefault();
                return;
            }
        });
        
        // Validación de formulario de subpaquete
        document.getElementById('formSubpaquete').addEventListener('submit', function(e) {
            const paquete = document.getElementById('subpaquetePaquete').value;
            const codigo = document.getElementById('subpaqueteCodigo').value;
            const nombre = document.getElementById('subpaqueteNombre').value;
            const precio = document.getElementById('subpaquetePrecio').value;
            const stockMin = document.getElementById('subpaqueteMin').value;
            const stockMax = document.getElementById('subpaqueteMax').value;
            
            if (!paquete) {
                alert('Seleccione un paquete');
                e.preventDefault();
                return;
            }
            
            if (!codigo.trim()) {
                alert('El código de color es obligatorio');
                e.preventDefault();
                return;
            }
            
            if (!nombre.trim()) {
                alert('El nombre de color es obligatorio');
                e.preventDefault();
                return;
            }
            
            if (!precio || parseFloat(precio) <= 0) {
                alert('Ingrese un precio de venta válido');
                e.preventDefault();
                return;
            }
            
            if (!stockMin || parseInt(stockMin) <= 0) {
                alert('Ingrese un stock mínimo válido');
                e.preventDefault();
                return;
            }
            
            if (!stockMax || parseInt(stockMax) <= 0) {
                alert('Ingrese un stock máximo válido');
                e.preventDefault();
                return;
            }
            
            if (parseInt(stockMax) <= parseInt(stockMin)) {
                alert('El stock máximo debe ser mayor que el stock mínimo');
                e.preventDefault();
                return;
            }
        });
    </script>
</body>
</html>