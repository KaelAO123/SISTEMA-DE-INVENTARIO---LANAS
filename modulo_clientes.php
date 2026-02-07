<?php
// modulo_clientes.php - Gestión de clientes y proveedores

require_once 'database.php';
require_once 'funciones.php';

// Verificar sesión
Funciones::verificarSesion();

$db = getDB();
$mensaje = '';
$error = '';

// Procesar acciones AJAX para obtener datos
if (isset($_GET['action']) && $_GET['action'] == 'ajax') {
    $ajax_action = $_GET['ajax_action'] ?? '';
    
    try {
        switch ($ajax_action) {
            case 'obtener_cliente':
                $id = $_GET['id'] ?? 0;
                $stmt = $db->prepare("SELECT * FROM clientes WHERE id = ? AND activo = 1");
                $stmt->execute([$id]);
                $cliente = $stmt->fetch();
                
                if ($cliente) {
                    echo json_encode(['success' => true, 'data' => $cliente]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Cliente no encontrado']);
                }
                exit;
                
            case 'obtener_proveedor':
                $id = $_GET['id'] ?? 0;
                $stmt = $db->prepare("SELECT * FROM proveedores WHERE id = ? AND activo = 1");
                $stmt->execute([$id]);
                $proveedor = $stmt->fetch();
                
                if ($proveedor) {
                    echo json_encode(['success' => true, 'data' => $proveedor]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Proveedor no encontrado']);
                }
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Procesar acciones CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'agregar_cliente':
                $nombre = Funciones::sanitizar($_POST['nombre']);
                $tipo_documento = $_POST['tipo_documento'];
                $numero_documento = Funciones::sanitizar($_POST['numero_documento']);
                $telefono = Funciones::sanitizar($_POST['telefono']);
                $email = Funciones::sanitizar($_POST['email']);
                $direccion = Funciones::sanitizar($_POST['direccion']);
                $limite_credito = $_POST['limite_credito'] ?? 0;
                $observaciones = Funciones::sanitizar($_POST['observaciones']);
                
                // Verificar documento único (solo si no está vacío)
                if (!empty($numero_documento)) {
                    $stmt = $db->prepare("SELECT id FROM clientes WHERE numero_documento = ? AND activo = 1");
                    $stmt->execute([$numero_documento]);
                    if ($stmt->fetch()) {
                        throw new Exception("El número de documento ya existe");
                    }
                }
                
                $stmt = $db->prepare("INSERT INTO clientes 
                                    (nombre, tipo_documento, numero_documento, telefono, email,
                                     direccion, limite_credito, observaciones, fecha_registro)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE())");
                $stmt->execute([
                    $nombre, $tipo_documento, $numero_documento, $telefono, $email,
                    $direccion, $limite_credito, $observaciones
                ]);
                
                $mensaje = "Cliente agregado exitosamente";
                break;
                
            case 'editar_cliente':
                $id = $_POST['id'];
                $nombre = Funciones::sanitizar($_POST['nombre']);
                $tipo_documento = $_POST['tipo_documento'];
                $numero_documento = Funciones::sanitizar($_POST['numero_documento']);
                $telefono = Funciones::sanitizar($_POST['telefono']);
                $email = Funciones::sanitizar($_POST['email']);
                $direccion = Funciones::sanitizar($_POST['direccion']);
                $limite_credito = $_POST['limite_credito'] ?? 0;
                $observaciones = Funciones::sanitizar($_POST['observaciones']);
                
                // Verificar documento único (excluyendo el actual)
                if (!empty($numero_documento)) {
                    $stmt = $db->prepare("SELECT id FROM clientes WHERE numero_documento = ? AND id != ? AND activo = 1");
                    $stmt->execute([$numero_documento, $id]);
                    if ($stmt->fetch()) {
                        throw new Exception("El número de documento ya existe en otro cliente");
                    }
                }
                
                $stmt = $db->prepare("UPDATE clientes 
                                    SET nombre = ?, tipo_documento = ?, numero_documento = ?,
                                        telefono = ?, email = ?, direccion = ?, 
                                        limite_credito = ?, observaciones = ?
                                    WHERE id = ? AND activo = 1");
                $stmt->execute([
                    $nombre, $tipo_documento, $numero_documento, $telefono, $email, 
                    $direccion, $limite_credito, $observaciones, $id
                ]);
                
                if ($stmt->rowCount() > 0) {
                    $mensaje = "Cliente actualizado exitosamente";
                } else {
                    throw new Exception("No se pudo actualizar el cliente");
                }
                break;
                
            case 'eliminar_cliente':
                $id = $_POST['id'];
                
                // Verificar si tiene deudas
                $stmt = $db->prepare("SELECT saldo_deuda FROM clientes WHERE id = ? AND activo = 1");
                $stmt->execute([$id]);
                $cliente = $stmt->fetch();
                
                if (!$cliente) {
                    throw new Exception("Cliente no encontrado");
                }
                
                if ($cliente['saldo_deuda'] > 0) {
                    throw new Exception("No se puede eliminar porque tiene saldo pendiente de $" . number_format($cliente['saldo_deuda'], 2));
                }
                
                $stmt = $db->prepare("UPDATE clientes SET activo = 0 WHERE id = ?");
                $stmt->execute([$id]);
                
                if ($stmt->rowCount() > 0) {
                    $mensaje = "Cliente desactivado exitosamente";
                } else {
                    throw new Exception("No se pudo desactivar el cliente");
                }
                break;
                
            case 'agregar_proveedor':
                $nombre = Funciones::sanitizar($_POST['nombre']);
                $ruc = Funciones::sanitizar($_POST['ruc']);
                $telefono = Funciones::sanitizar($_POST['telefono']);
                $email = Funciones::sanitizar($_POST['email']);
                $direccion = Funciones::sanitizar($_POST['direccion']);
                $ciudad = Funciones::sanitizar($_POST['ciudad']);
                
                // Verificar RUC único (solo si no está vacío)
                if (!empty($ruc)) {
                    $stmt = $db->prepare("SELECT id FROM proveedores WHERE ruc = ? AND activo = 1");
                    $stmt->execute([$ruc]);
                    if ($stmt->fetch()) {
                        throw new Exception("El RUC ya existe");
                    }
                }
                
                $stmt = $db->prepare("INSERT INTO proveedores 
                                    (nombre, ruc, telefono, email, direccion, ciudad)
                                    VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nombre, $ruc, $telefono, $email, $direccion, $ciudad]);
                
                $mensaje = "Proveedor agregado exitosamente";
                break;
                
            case 'editar_proveedor':
                $id = $_POST['id'];
                $nombre = Funciones::sanitizar($_POST['nombre']);
                $ruc = Funciones::sanitizar($_POST['ruc']);
                $telefono = Funciones::sanitizar($_POST['telefono']);
                $email = Funciones::sanitizar($_POST['email']);
                $direccion = Funciones::sanitizar($_POST['direccion']);
                $ciudad = Funciones::sanitizar($_POST['ciudad']);
                
                // Verificar RUC único (excluyendo el actual)
                if (!empty($ruc)) {
                    $stmt = $db->prepare("SELECT id FROM proveedores WHERE ruc = ? AND id != ? AND activo = 1");
                    $stmt->execute([$ruc, $id]);
                    if ($stmt->fetch()) {
                        throw new Exception("El RUC ya existe en otro proveedor");
                    }
                }
                
                $stmt = $db->prepare("UPDATE proveedores 
                                    SET nombre = ?, ruc = ?, telefono = ?, email = ?, 
                                        direccion = ?, ciudad = ?
                                    WHERE id = ? AND activo = 1");
                $stmt->execute([$nombre, $ruc, $telefono, $email, $direccion, $ciudad, $id]);
                
                if ($stmt->rowCount() > 0) {
                    $mensaje = "Proveedor actualizado exitosamente";
                } else {
                    throw new Exception("No se pudo actualizar el proveedor");
                }
                break;
                
            case 'eliminar_proveedor':
                $id = $_POST['id'];
                
                // Verificar si existe
                $stmt = $db->prepare("SELECT * FROM proveedores WHERE id = ? AND activo = 1");
                $stmt->execute([$id]);
                $proveedor = $stmt->fetch();
                
                if (!$proveedor) {
                    throw new Exception("Proveedor no encontrado");
                }
                
                // Verificar si tiene productos asociados
                $stmt = $db->prepare("SELECT COUNT(*) as productos FROM paquetes WHERE proveedor_id = ?");
                $stmt->execute([$id]);
                $productos = $stmt->fetch()['productos'];
                
                if ($productos > 0) {
                    throw new Exception("No se puede eliminar porque tiene $productos productos asociados");
                }
                
                $stmt = $db->prepare("UPDATE proveedores SET activo = 0 WHERE id = ?");
                $stmt->execute([$id]);
                
                if ($stmt->rowCount() > 0) {
                    $mensaje = "Proveedor desactivado exitosamente";
                } else {
                    throw new Exception("No se pudo desactivar el proveedor");
                }
                break;
                
            case 'registrar_pago_cliente':
                $cliente_id = $_POST['cliente_id'];
                $monto = floatval($_POST['monto']);
                $metodo = $_POST['metodo'];
                $referencia = Funciones::sanitizar($_POST['referencia']);
                
                $db->beginTransaction();
                
                // Obtener saldo actual
                $stmt = $db->prepare("SELECT nombre, saldo_deuda FROM clientes WHERE id = ? AND activo = 1");
                $stmt->execute([$cliente_id]);
                $cliente = $stmt->fetch();
                
                if (!$cliente) {
                    throw new Exception('Cliente no encontrado');
                }
                
                if ($monto <= 0) {
                    throw new Exception('El monto debe ser mayor a cero');
                }
                
                if ($monto > $cliente['saldo_deuda']) {
                    throw new Exception('El monto excede la deuda del cliente. Deuda actual: $' . number_format($cliente['saldo_deuda'], 2));
                }
                
                // Actualizar saldo
                $nuevo_saldo = $cliente['saldo_deuda'] - $monto;
                $stmt = $db->prepare("UPDATE clientes SET saldo_deuda = ? WHERE id = ?");
                $stmt->execute([$nuevo_saldo, $cliente_id]);
                
                // Registrar pago
                $usuario_id = Funciones::obtenerUsuarioId() ?? 1;
                $stmt = $db->prepare("INSERT INTO cuentas_cobrar 
                                     (cliente_id, tipo, monto, saldo_anterior, saldo_nuevo,
                                      metodo_pago, referencia_pago, fecha_hora, usuario_id)
                                     VALUES (?, 'pago', ?, ?, ?, ?, ?, NOW(), ?)");
                $stmt->execute([
                    $cliente_id, $monto, $cliente['saldo_deuda'], $nuevo_saldo,
                    $metodo, $referencia, $usuario_id
                ]);
                
                $db->commit();
                $mensaje = "Pago de $" . number_format($monto, 2) . " registrado exitosamente para " . $cliente['nombre'];
                break;
                
            case 'registrar_pago_proveedor':
                $proveedor_id = $_POST['proveedor_id'];
                $monto = floatval($_POST['monto']);
                $metodo = $_POST['metodo'];
                $referencia = Funciones::sanitizar($_POST['referencia']);
                
                $db->beginTransaction();
                
                // Obtener saldo actual
                $stmt = $db->prepare("SELECT nombre, saldo_deuda FROM proveedores WHERE id = ? AND activo = 1");
                $stmt->execute([$proveedor_id]);
                $proveedor = $stmt->fetch();
                
                if (!$proveedor) {
                    throw new Exception('Proveedor no encontrado');
                }
                
                if ($monto <= 0) {
                    throw new Exception('El monto debe ser mayor a cero');
                }
                
                if ($monto > $proveedor['saldo_deuda']) {
                    throw new Exception('El monto excede la deuda del proveedor. Deuda actual: $' . number_format($proveedor['saldo_deuda'], 2));
                }
                
                // Actualizar saldo
                $nuevo_saldo = $proveedor['saldo_deuda'] - $monto;
                $stmt = $db->prepare("UPDATE proveedores SET saldo_deuda = ? WHERE id = ?");
                $stmt->execute([$nuevo_saldo, $proveedor_id]);
                
                // Registrar pago
                $usuario_id = Funciones::obtenerUsuarioId() ?? 1;
                $stmt = $db->prepare("INSERT INTO cuentas_pagar 
                                     (proveedor_id, tipo, monto, saldo_anterior, saldo_nuevo,
                                      metodo_pago, referencia_pago, fecha_hora, usuario_id)
                                     VALUES (?, 'pago', ?, ?, ?, ?, ?, NOW(), ?)");
                $stmt->execute([
                    $proveedor_id, $monto, $proveedor['saldo_deuda'], $nuevo_saldo,
                    $metodo, $referencia, $usuario_id
                ]);
                
                $db->commit();
                $mensaje = "Pago de $" . number_format($monto, 2) . " registrado exitosamente para " . $proveedor['nombre'];
                break;
                
            default:
                throw new Exception("Acción no válida");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
    }
}

// Obtener datos para mostrar
try {
    // Clientes
    $stmt = $db->query("SELECT * FROM clientes WHERE activo = 1 ORDER BY nombre");
    $clientes = $stmt->fetchAll();
    
    // Proveedores
    $stmt = $db->query("SELECT * FROM proveedores WHERE activo = 1 ORDER BY nombre");
    $proveedores = $stmt->fetchAll();
    
    // Clientes con deuda
    $stmt = $db->prepare("SELECT * FROM clientes 
                         WHERE saldo_deuda > 0 
                         AND activo = 1 
                         ORDER BY saldo_deuda DESC 
                         LIMIT 10");
    $stmt->execute();
    $clientes_deuda = $stmt->fetchAll();
    
    // Proveedores con deuda
    $stmt = $db->prepare("SELECT * FROM proveedores 
                         WHERE saldo_deuda > 0 
                         AND activo = 1 
                         ORDER BY saldo_deuda DESC 
                         LIMIT 10");
    $stmt->execute();
    $proveedores_deuda = $stmt->fetchAll();
    
    // Historial de pagos clientes
    $stmt = $db->prepare("SELECT cc.*, c.nombre as cliente_nombre, u.nombre as usuario_nombre
                         FROM cuentas_cobrar cc
                         LEFT JOIN clientes c ON cc.cliente_id = c.id
                         LEFT JOIN usuarios u ON cc.usuario_id = u.id
                         WHERE cc.tipo = 'pago'
                         ORDER BY cc.fecha_hora DESC
                         LIMIT 10");
    $stmt->execute();
    $pagos_clientes = $stmt->fetchAll();
    
    // Historial de pagos proveedores
    $stmt = $db->prepare("SELECT cp.*, p.nombre as proveedor_nombre, u.nombre as usuario_nombre
                         FROM cuentas_pagar cp
                         LEFT JOIN proveedores p ON cp.proveedor_id = p.id
                         LEFT JOIN usuarios u ON cp.usuario_id = u.id
                         WHERE cp.tipo = 'pago'
                         ORDER BY cp.fecha_hora DESC
                         LIMIT 10");
    $stmt->execute();
    $pagos_proveedores = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Error al cargar datos: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes y Proveedores - Sistema de Inventario</title>
    
    <?php include 'header.php'; ?>
    
    <style>
        :root {
            --primary-color: #28a745;  /* Verde */
            --primary-light: #d4edda;
            --secondary-color: #6c757d; /* Gris */
            --accent-color: #17a2b8;   /* Azul claro */
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
        }
        
        body {
            background-color: #f8f9fa;
            color: #333;
        }
        
        .main-content {
            background-color: #fff;
            min-height: calc(100vh - 56px);
            border-radius: 10px;
            padding: 15px;
        }
        
        .stats-card {
            transition: all 0.3s ease;
            border: none;
            border-radius: 12px;
            overflow: hidden;
            background: white;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary-color);
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .stats-icon {
            font-size: 2rem;
            opacity: 0.8;
        }
        
        .customer-card, .supplier-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        
        .customer-card:hover, .supplier-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }
        
        .customer-card {
            border-left: 3px solid var(--primary-color);
        }
        
        .supplier-card {
            border-left: 3px solid var(--info-color);
        }
        
        .debt-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .contact-info {
            color: #666;
            font-size: 0.85rem;
            line-height: 1.4;
        }
        
        .contact-info i {
            width: 20px;
            text-align: center;
            margin-right: 0.5rem;
            color: var(--secondary-color);
        }
        
        .tab-content {
            background: white;
            border-radius: 0 0 10px 10px;
            padding: 1.5rem;
            border: 1px solid #dee2e6;
            border-top: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        }
        
        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
        }
        
        .nav-tabs .nav-link {
            border: none;
            border-radius: 8px 8px 0 0;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            color: var(--secondary-color);
            transition: all 0.3s;
            margin-right: 5px;
            background-color: #f8f9fa;
        }
        
        .nav-tabs .nav-link:hover {
            background-color: #e9ecef;
            color: var(--primary-color);
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            background-color: white;
            border-bottom: 2px solid var(--primary-color);
            font-weight: 600;
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
            color: var(--secondary-color);
        }
        
        .search-container input {
            padding-left: 3rem;
            border-radius: 25px;
            border: 1px solid #dee2e6;
        }
        
        .table-actions {
            white-space: nowrap;
        }
        
        .table-actions .btn {
            padding: 0.25rem 0.5rem;
            border-radius: 5px;
            margin-left: 2px;
        }
        
        .avatar {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary-color), #34ce57);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.25rem;
        }
        
        .supplier-card .avatar {
            background: linear-gradient(135deg, var(--info-color), #2fc5e6);
        }
        
        .payment-history {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .payment-item {
            padding: 0.75rem;
            border-bottom: 1px solid #e9ecef;
            font-size: 0.875rem;
            transition: background-color 0.2s;
        }
        
        .payment-item:hover {
            background-color: #f8f9fa;
        }
        
        .payment-item:last-child {
            border-bottom: none;
        }
        
        .payment-method {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.75rem;
            margin-right: 0.5rem;
        }
        
        .cash { background-color: #28a745; }
        .transfer { background-color: #007bff; }
        .card { background-color: #f7f7f7; }
        .check { background-color: #fd7e14; }
        
        .btn-success {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        
        .btn-outline-success {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-outline-success:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), #34ce57);
            color: white;
        }
        
        .modal-header.bg-primary {
            background: linear-gradient(135deg, var(--info-color), #2fc5e6) !important;
        }
        
        .card-header {
            font-weight: 600;
        }
        
        .card-header.bg-success {
            background: linear-gradient(135deg, #28a745, #5cb85c) !important;
        }
        
        .card-header.bg-danger {
            background: linear-gradient(135deg, #dc3545, #e35d6a) !important;
        }
        
        .card-header.bg-warning {
            background: linear-gradient(135deg, #ffc107, #ffce3a) !important;
            color: #333;
        }
        
        .card-header.bg-info {
            background: linear-gradient(135deg, #17a2b8, #5bc0de) !important;
        }
        
        .card-header.bg-primary {
            background: linear-gradient(135deg, #007bff, #5bc0de) !important;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        
        .h3.text-success {
            color: var(--primary-color) !important;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
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
                    <h1 class="h3 mb-2" style="color: var(--primary-color);">
                        <i class="fas fa-users me-2"></i>Clientes y Proveedores
                    </h1>
                    <p class="text-muted">
                        Gestione clientes, proveedores y cuentas pendientes
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalCliente" onclick="resetClienteForm()">
                        <i class="fas fa-user-plus me-2"></i>Nuevo Cliente
                    </button>
                    <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalProveedor" onclick="resetProveedorForm()">
                        <i class="fas fa-truck me-2"></i>Nuevo Proveedor
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
                    <div class="card stats-card">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs fw-bold text-muted mb-1">
                                        Clientes Activos
                                    </div>
                                    <div class="h5 mb-0 fw-bold">
                                        <?php echo count($clientes); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-friends stats-icon" style="color: var(--primary-color);"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stats-card">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs fw-bold text-muted mb-1">
                                        Proveedores Activos
                                    </div>
                                    <div class="h5 mb-0 fw-bold">
                                        <?php echo count($proveedores); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-truck stats-icon" style="color: var(--info-color);"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stats-card">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs fw-bold text-muted mb-1">
                                        Deuda Clientes
                                    </div>
                                    <div class="h5 mb-0 fw-bold" style="color: var(--danger-color);">
                                        <?php
                                        $total_deuda_clientes = 0;
                                        foreach ($clientes as $cliente) {
                                            $total_deuda_clientes += floatval($cliente['saldo_deuda']);
                                        }
                                        echo '$' . number_format($total_deuda_clientes, 2);
                                        ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-hand-holding-usd stats-icon" style="color: var(--danger-color);"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stats-card">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs fw-bold text-muted mb-1">
                                        Deuda Proveedores
                                    </div>
                                    <div class="h5 mb-0 fw-bold" style="color: var(--warning-color);">
                                        <?php
                                        $total_deuda_proveedores = 0;
                                        foreach ($proveedores as $proveedor) {
                                            $total_deuda_proveedores += floatval($proveedor['saldo_deuda']);
                                        }
                                        echo '$' . number_format($total_deuda_proveedores, 2);
                                        ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-file-invoice-dollar stats-icon" style="color: var(--warning-color);"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pestañas -->
            <ul class="nav nav-tabs mb-3" id="clientTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="clientes-tab" data-bs-toggle="tab" 
                            data-bs-target="#clientes" type="button" role="tab">
                        <i class="fas fa-user-friends me-2"></i>Clientes
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="proveedores-tab" data-bs-toggle="tab" 
                            data-bs-target="#proveedores" type="button" role="tab">
                        <i class="fas fa-truck me-2"></i>Proveedores
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="deudas-tab" data-bs-toggle="tab" 
                            data-bs-target="#deudas" type="button" role="tab">
                        <i class="fas fa-file-invoice-dollar me-2"></i>Cuentas Pendientes
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pagos-tab" data-bs-toggle="tab" 
                            data-bs-target="#pagos" type="button" role="tab">
                        <i class="fas fa-history me-2"></i>Historial de Pagos
                    </button>
                </li>
            </ul>
            
            <!-- Contenido de pestañas -->
            <div class="tab-content" id="clientTabsContent">
                
                <!-- Pestaña Clientes -->
                <div class="tab-pane fade show active" id="clientes" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Lista de Clientes</h5>
                        <div class="search-container">
                            <i class="fas fa-search"></i>
                            <input type="text" class="form-control" id="searchClientes" 
                                   placeholder="Buscar clientes...">
                        </div>
                    </div>
                    
                    <?php if (empty($clientes)): ?>
                        <div class="empty-state">
                            <i class="fas fa-user-friends"></i>
                            <h4>No hay clientes registrados</h4>
                            <p>Comience agregando un nuevo cliente</p>
                            <button class="btn btn-success mt-2" data-bs-toggle="modal" data-bs-target="#modalCliente">
                                <i class="fas fa-user-plus me-2"></i>Agregar Cliente
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="row" id="clientesGrid">
                            <?php foreach ($clientes as $cliente): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="customer-card">
                                        <div class="d-flex align-items-start mb-3">
                                            <div class="avatar me-3">
                                                <?php echo strtoupper(substr($cliente['nombre'], 0, 1)); ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($cliente['nombre']); ?></h6>
                                                <small class="text-muted">
                                                    <?php echo $cliente['tipo_documento']; ?>: 
                                                    <?php echo $cliente['numero_documento'] ?: 'Sin documento'; ?>
                                                </small>
                                            </div>
                                            <?php if ($cliente['saldo_deuda'] > 0): ?>
                                                <span class="debt-badge bg-danger text-white">
                                                    Deuda: $<?php echo number_format($cliente['saldo_deuda'], 2); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="contact-info mb-3">
                                            <?php if ($cliente['telefono']): ?>
                                                <div class="mb-1">
                                                    <i class="fas fa-phone"></i>
                                                    <?php echo htmlspecialchars($cliente['telefono']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($cliente['email']): ?>
                                                <div class="mb-1">
                                                    <i class="fas fa-envelope"></i>
                                                    <?php echo htmlspecialchars($cliente['email']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($cliente['direccion']): ?>
                                                <div>
                                                    <i class="fas fa-map-marker-alt"></i>
                                                    <?php echo htmlspecialchars($cliente['direccion']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <small class="text-muted">
                                                    Límite: $<?php echo number_format($cliente['limite_credito'], 2); ?>
                                                </small>
                                            </div>
                                            <div class="table-actions">
                                                <?php if ($cliente['saldo_deuda'] > 0): ?>
                                                    <button class="btn btn-sm btn-outline-success" 
                                                            onclick="registrarPagoCliente(<?php echo $cliente['id']; ?>)"
                                                            title="Registrar pago">
                                                        <i class="fas fa-money-bill-wave"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="editarCliente(<?php echo $cliente['id']; ?>)"
                                                        title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" 
                                                        onclick="eliminarCliente(<?php echo $cliente['id']; ?>)"
                                                        title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Pestaña Proveedores -->
                <div class="tab-pane fade" id="proveedores" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Lista de Proveedores</h5>
                        <div class="search-container">
                            <i class="fas fa-search"></i>
                            <input type="text" class="form-control" id="searchProveedores" 
                                   placeholder="Buscar proveedores...">
                        </div>
                    </div>
                    
                    <?php if (empty($proveedores)): ?>
                        <div class="empty-state">
                            <i class="fas fa-truck"></i>
                            <h4>No hay proveedores registrados</h4>
                            <p>Comience agregando un nuevo proveedor</p>
                            <button class="btn btn-outline-success mt-2" data-bs-toggle="modal" data-bs-target="#modalProveedor">
                                <i class="fas fa-truck me-2"></i>Agregar Proveedor
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="row" id="proveedoresGrid">
                            <?php foreach ($proveedores as $proveedor): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="supplier-card">
                                        <div class="d-flex align-items-start mb-3">
                                            <div class="avatar me-3">
                                                <i class="fas fa-truck"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($proveedor['nombre']); ?></h6>
                                                <small class="text-muted">
                                                    RUC: <?php echo $proveedor['ruc'] ?: 'Sin RUC'; ?>
                                                </small>
                                            </div>
                                            <?php if ($proveedor['saldo_deuda'] > 0): ?>
                                                <span class="debt-badge bg-warning text-dark">
                                                    Deuda: $<?php echo number_format($proveedor['saldo_deuda'], 2); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="contact-info mb-3">
                                            <?php if ($proveedor['telefono']): ?>
                                                <div class="mb-1">
                                                    <i class="fas fa-phone"></i>
                                                    <?php echo htmlspecialchars($proveedor['telefono']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($proveedor['email']): ?>
                                                <div class="mb-1">
                                                    <i class="fas fa-envelope"></i>
                                                    <?php echo htmlspecialchars($proveedor['email']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($proveedor['direccion']): ?>
                                                <div class="mb-1">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                    <?php echo htmlspecialchars($proveedor['direccion']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($proveedor['ciudad']): ?>
                                                <div>
                                                    <i class="fas fa-city"></i>
                                                    <?php echo htmlspecialchars($proveedor['ciudad']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <?php 
                                                // Contar productos de este proveedor
                                                $stmt = $db->prepare("SELECT COUNT(*) as productos FROM paquetes WHERE proveedor_id = ?");
                                                $stmt->execute([$proveedor['id']]);
                                                $productos = $stmt->fetch()['productos'];
                                                ?>
                                                <small class="text-muted">
                                                    Productos: <?php echo $productos; ?>
                                                </small>
                                            </div>
                                            <div class="table-actions">
                                                <?php if ($proveedor['saldo_deuda'] > 0): ?>
                                                    <button class="btn btn-sm btn-outline-success" 
                                                            onclick="registrarPagoProveedor(<?php echo $proveedor['id']; ?>)"
                                                            title="Registrar pago">
                                                        <i class="fas fa-money-bill-wave"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="editarProveedor(<?php echo $proveedor['id']; ?>)"
                                                        title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" 
                                                        onclick="eliminarProveedor(<?php echo $proveedor['id']; ?>)"
                                                        title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Pestaña Cuentas Pendientes -->
                <div class="tab-pane fade" id="deudas" role="tabpanel">
                    <div class="row">
                        <!-- Clientes con deuda -->
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header bg-danger text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-user-times me-2"></i>
                                        Clientes con Deuda
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($clientes_deuda)): ?>
                                        <div class="empty-state py-3">
                                            <i class="fas fa-check-circle"></i>
                                            <p>No hay clientes con deuda pendiente</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Cliente</th>
                                                        <th>Deuda</th>
                                                        <th>Límite</th>
                                                        <th>Acción</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($clientes_deuda as $cliente): ?>
                                                        <tr>
                                                            <td>
                                                                <div class="fw-bold"><?php echo htmlspecialchars($cliente['nombre']); ?></div>
                                                                <small class="text-muted"><?php echo $cliente['telefono']; ?></small>
                                                            </td>
                                                            <td class="fw-bold text-danger">
                                                                $<?php echo number_format($cliente['saldo_deuda'], 2); ?>
                                                            </td>
                                                            <td>$<?php echo number_format($cliente['limite_credito'], 2); ?></td>
                                                            <td>
                                                                <button class="btn btn-sm btn-success" 
                                                                        onclick="registrarPagoCliente(<?php echo $cliente['id']; ?>)">
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
                        
                        <!-- Proveedores con deuda -->
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header bg-warning text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-file-invoice-dollar me-2"></i>
                                        Proveedores con Deuda
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($proveedores_deuda)): ?>
                                        <div class="empty-state py-3">
                                            <i class="fas fa-check-circle"></i>
                                            <p>No hay proveedores con deuda pendiente</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Proveedor</th>
                                                        <th>Deuda</th>
                                                        <th>Contacto</th>
                                                        <th>Acción</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($proveedores_deuda as $proveedor): ?>
                                                        <tr>
                                                            <td>
                                                                <div class="fw-bold"><?php echo htmlspecialchars($proveedor['nombre']); ?></div>
                                                                <small class="text-muted">RUC: <?php echo $proveedor['ruc']; ?></small>
                                                            </td>
                                                            <td class="fw-bold text-warning">
                                                                $<?php echo number_format($proveedor['saldo_deuda'], 2); ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($proveedor['telefono']): ?>
                                                                    <div><i class="fas fa-phone"></i> <?php echo $proveedor['telefono']; ?></div>
                                                                <?php endif; ?>
                                                                <?php if ($proveedor['email']): ?>
                                                                    <div><i class="fas fa-envelope"></i> <?php echo $proveedor['email']; ?></div>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <button class="btn btn-sm btn-success" 
                                                                        onclick="registrarPagoProveedor(<?php echo $proveedor['id']; ?>)">
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
                    </div>
                    
                    <!-- Resumen de deudas -->
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-bar me-2"></i>
                                Resumen de Deudas
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <canvas id="deudasChart" height="250"></canvas>
                                </div>
                                <div class="col-md-6">
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Concepto</th>
                                                    <th>Total</th>
                                                    <th>Clientes/Proveedores</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>Deudas por Cobrar</td>
                                                    <td class="fw-bold text-danger">$<?php echo number_format($total_deuda_clientes, 2); ?></td>
                                                    <td><?php echo count($clientes_deuda); ?> clientes</td>
                                                </tr>
                                                <tr>
                                                    <td>Deudas por Pagar</td>
                                                    <td class="fw-bold text-warning">$<?php echo number_format($total_deuda_proveedores, 2); ?></td>
                                                    <td><?php echo count($proveedores_deuda); ?> proveedores</td>
                                                </tr>
                                                <tr class="table-active">
                                                    <td>Saldo Neto</td>
                                                    <td class="fw-bold <?php echo ($total_deuda_clientes - $total_deuda_proveedores) >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                        $<?php echo number_format($total_deuda_clientes - $total_deuda_proveedores, 2); ?>
                                                    </td>
                                                    <td>
                                                        <?php echo ($total_deuda_clientes - $total_deuda_proveedores) >= 0 ? 'A Favor' : 'En Contra'; ?>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pestaña Historial de Pagos -->
                <div class="tab-pane fade" id="pagos" role="tabpanel">
                    <div class="row">
                        <!-- Pagos de clientes -->
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-hand-holding-usd me-2"></i>
                                        Pagos de Clientes
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="payment-history">
                                        <?php if (empty($pagos_clientes)): ?>
                                            <div class="empty-state py-3">
                                                <i class="fas fa-history"></i>
                                                <p>No hay pagos registrados</p>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($pagos_clientes as $pago): ?>
                                                <div class="payment-item">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <div class="fw-bold"><?php echo htmlspecialchars($pago['cliente_nombre']); ?></div>
                                                        <span class="badge bg-success">$<?php echo number_format($pago['monto'], 2); ?></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <span class="payment-method <?php echo $pago['metodo_pago'] ?? 'cash'; ?>">
                                                                <?php echo substr(strtoupper($pago['metodo_pago'] ?? 'Efectivo'), 0, 1); ?>
                                                            </span>
                                                            <small class="text-muted">
                                                                <?php echo date('d/m/Y H:i', strtotime($pago['fecha_hora'])); ?>
                                                            </small>
                                                        </div>
                                                        <small class="text-muted">
                                                            Por: <?php echo htmlspecialchars($pago['usuario_nombre']); ?>
                                                        </small>
                                                    </div>
                                                    <?php if ($pago['referencia_pago']): ?>
                                                        <small class="text-muted mt-1 d-block">
                                                            Ref: <?php echo htmlspecialchars($pago['referencia_pago']); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Pagos a proveedores -->
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-file-invoice-dollar me-2"></i>
                                        Pagos a Proveedores
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="payment-history">
                                        <?php if (empty($pagos_proveedores)): ?>
                                            <div class="empty-state py-3">
                                                <i class="fas fa-history"></i>
                                                <p>No hay pagos registrados</p>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($pagos_proveedores as $pago): ?>
                                                <div class="payment-item">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <div class="fw-bold"><?php echo htmlspecialchars($pago['proveedor_nombre']); ?></div>
                                                        <span class="badge bg-primary">$<?php echo number_format($pago['monto'], 2); ?></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <span class="payment-method <?php echo $pago['metodo_pago'] ?? 'transfer'; ?>">
                                                                <?php echo substr(strtoupper($pago['metodo_pago'] ?? 'Transferencia'), 0, 1); ?>
                                                            </span>
                                                            <small class="text-muted">
                                                                <?php echo date('d/m/Y H:i', strtotime($pago['fecha_hora'])); ?>
                                                            </small>
                                                        </div>
                                                        <small class="text-muted">
                                                            Por: <?php echo htmlspecialchars($pago['usuario_nombre']); ?>
                                                        </small>
                                                    </div>
                                                    <?php if ($pago['referencia_pago']): ?>
                                                        <small class="text-muted mt-1 d-block">
                                                            Ref: <?php echo htmlspecialchars($pago['referencia_pago']); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para cliente -->
    <div class="modal fade" id="modalCliente" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>Nuevo Cliente
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="formCliente" method="POST">
                    <input type="hidden" name="action" value="agregar_cliente">
                    <input type="hidden" name="id" id="cliente_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label">Nombre *</label>
                                <input type="text" class="form-control" name="nombre" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tipo Documento</label>
                                <select class="form-select" name="tipo_documento" required>
                                    <option value="DNI">DNI</option>
                                    <option value="RUC">RUC</option>
                                    <option value="Cedula">Cédula</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Número Documento</label>
                                <input type="text" class="form-control" name="numero_documento" placeholder="Opcional">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Teléfono</label>
                                <input type="tel" class="form-control" name="telefono" placeholder="Opcional">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" placeholder="Opcional">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Dirección</label>
                                <textarea class="form-control" name="direccion" rows="2" placeholder="Opcional"></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Límite de Crédito</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" name="limite_credito" 
                                           value="1000" step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Observaciones</label>
                                <textarea class="form-control" name="observaciones" rows="2" placeholder="Opcional"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-success" id="btnSubmitCliente">
                            Guardar Cliente
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal para proveedor -->
    <div class="modal fade" id="modalProveedor" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title">
                        <i class="fas fa-truck me-2"></i>Nuevo Proveedor
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="formProveedor" method="POST">
                    <input type="hidden" name="action" value="agregar_proveedor">
                    <input type="hidden" name="id" id="proveedor_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label">Nombre *</label>
                                <input type="text" class="form-control" name="nombre" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">RUC</label>
                                <input type="text" class="form-control" name="ruc" 
                                       placeholder="Opcional">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Teléfono</label>
                                <input type="tel" class="form-control" name="telefono" placeholder="Opcional">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" placeholder="Opcional">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ciudad</label>
                                <input type="text" class="form-control" name="ciudad" 
                                       placeholder="Opcional">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Dirección</label>
                                <textarea class="form-control" name="direccion" rows="2" placeholder="Opcional"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary" id="btnSubmitProveedor">
                            Guardar Proveedor
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal para registrar pago cliente -->
    <div class="modal fade" id="modalPagoCliente" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success">
                    <h5 class="modal-title">
                        <i class="fas fa-money-bill-wave me-2"></i>Registrar Pago Cliente
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="formPagoCliente" method="POST">
                    <input type="hidden" name="action" value="registrar_pago_cliente">
                    <input type="hidden" name="cliente_id" id="pago_cliente_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Cliente</label>
                            <input type="text" class="form-control" id="pago_cliente_nombre" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deuda Actual</label>
                            <input type="text" class="form-control" id="pago_cliente_deuda" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Monto a Pagar *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="monto" 
                                       step="0.01" min="0.01" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Método de Pago *</label>
                            <select class="form-select" name="metodo" required>
                                <option value="efectivo">Efectivo</option>
                                <option value="transferencia">Transferencia</option>
                                <option value="tarjeta">Tarjeta</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Referencia (opcional)</label>
                            <input type="text" class="form-control" name="referencia" 
                                   placeholder="Nº de transferencia, cheque, etc.">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-success">
                            Registrar Pago
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal para registrar pago proveedor -->
    <div class="modal fade" id="modalPagoProveedor" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title">
                        <i class="fas fa-file-invoice-dollar me-2"></i>Registrar Pago Proveedor
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="formPagoProveedor" method="POST">
                    <input type="hidden" name="action" value="registrar_pago_proveedor">
                    <input type="hidden" name="proveedor_id" id="pago_proveedor_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Proveedor</label>
                            <input type="text" class="form-control" id="pago_proveedor_nombre" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deuda Actual</label>
                            <input type="text" class="form-control" id="pago_proveedor_deuda" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Monto a Pagar *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="monto" 
                                       step="0.01" min="0.01" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Método de Pago *</label>
                            <select class="form-select" name="metodo" required>
                                <option value="transferencia">Transferencia</option>
                                <option value="efectivo">Efectivo</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Referencia (opcional)</label>
                            <input type="text" class="form-control" name="referencia" 
                                   placeholder="Nº de transferencia, cheque, etc.">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            Registrar Pago
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Gráfico de deudas
        function inicializarGraficoDeudas() {
            const ctx = document.getElementById('deudasChart');
            if (!ctx) return;
            
            const ctxCanvas = ctx.getContext('2d');
            const totalClientes = <?php echo $total_deuda_clientes; ?>;
            const totalProveedores = <?php echo $total_deuda_proveedores; ?>;
            
            new Chart(ctxCanvas, {
                type: 'bar',
                data: {
                    labels: ['Por Cobrar', 'Por Pagar'],
                    datasets: [{
                        label: 'Monto Total ($)',
                        data: [totalClientes, totalProveedores],
                        backgroundColor: [
                            'rgba(220, 53, 69, 0.7)',
                            'rgba(255, 193, 7, 0.7)'
                        ],
                        borderColor: [
                            'rgb(220, 53, 69)',
                            'rgb(255, 193, 7)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Monto: $' + context.raw.toLocaleString('es-EC', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString('es-EC');
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Configurar búsqueda
        function configurarBusqueda() {
            // Búsqueda en clientes
            const searchClientes = document.getElementById('searchClientes');
            if (searchClientes) {
                searchClientes.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const clientes = document.querySelectorAll('#clientesGrid .customer-card');
                    
                    clientes.forEach(cliente => {
                        const nombre = cliente.querySelector('h6').textContent.toLowerCase();
                        const documento = cliente.querySelector('.text-muted').textContent.toLowerCase();
                        
                        if (nombre.includes(searchTerm) || documento.includes(searchTerm)) {
                            cliente.parentElement.style.display = 'block';
                        } else {
                            cliente.parentElement.style.display = 'none';
                        }
                    });
                });
            }
            
            // Búsqueda en proveedores
            const searchProveedores = document.getElementById('searchProveedores');
            if (searchProveedores) {
                searchProveedores.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const proveedores = document.querySelectorAll('#proveedoresGrid .supplier-card');
                    
                    proveedores.forEach(proveedor => {
                        const nombre = proveedor.querySelector('h6').textContent.toLowerCase();
                        const ruc = proveedor.querySelector('.text-muted').textContent.toLowerCase();
                        
                        if (nombre.includes(searchTerm) || ruc.includes(searchTerm)) {
                            proveedor.parentElement.style.display = 'block';
                        } else {
                            proveedor.parentElement.style.display = 'none';
                        }
                    });
                });
            }
        }
        
        // Funciones CRUD para clientes
        function resetClienteForm() {
            document.getElementById('formCliente').reset();
            document.querySelector('#formCliente input[name="action"]').value = 'agregar_cliente';
            document.getElementById('cliente_id').value = '';
            document.querySelector('#modalCliente .modal-title').innerHTML = 
                '<i class="fas fa-user-plus me-2"></i>Nuevo Cliente';
            document.getElementById('btnSubmitCliente').innerHTML = 'Guardar Cliente';
        }
        
        function editarCliente(id) {
            fetch(`modulo_clientes.php?action=ajax&ajax_action=obtener_cliente&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const cliente = data.data;
                        
                        // Llenar formulario de edición
                        document.querySelector('#formCliente input[name="action"]').value = 'editar_cliente';
                        document.getElementById('cliente_id').value = id;
                        document.querySelector('#formCliente input[name="nombre"]').value = cliente.nombre;
                        document.querySelector('#formCliente select[name="tipo_documento"]').value = cliente.tipo_documento || 'DNI';
                        document.querySelector('#formCliente input[name="numero_documento"]').value = cliente.numero_documento || '';
                        document.querySelector('#formCliente input[name="telefono"]').value = cliente.telefono || '';
                        document.querySelector('#formCliente input[name="email"]').value = cliente.email || '';
                        document.querySelector('#formCliente textarea[name="direccion"]').value = cliente.direccion || '';
                        document.querySelector('#formCliente input[name="limite_credito"]').value = cliente.limite_credito || 1000;
                        document.querySelector('#formCliente textarea[name="observaciones"]').value = cliente.observaciones || '';
                        
                        // Cambiar título del modal
                        document.querySelector('#modalCliente .modal-title').innerHTML = 
                            '<i class="fas fa-edit me-2"></i>Editar Cliente';
                        document.getElementById('btnSubmitCliente').innerHTML = 'Actualizar Cliente';
                        
                        const modal = new bootstrap.Modal(document.getElementById('modalCliente'));
                        modal.show();
                    } else {
                        alert(data.message || 'Error al cargar datos del cliente');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cargar datos del cliente');
                });
        }
        
        function eliminarCliente(id) {
            if (confirm('¿Está seguro de desactivar este cliente? Esta acción no se puede deshacer.')) {
                const formData = new FormData();
                formData.append('action', 'eliminar_cliente');
                formData.append('id', id);
                
                fetch('modulo_clientes.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(() => {
                    location.reload();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al desactivar el cliente');
                });
            }
        }
        
        // Funciones CRUD para proveedores
        function resetProveedorForm() {
            document.getElementById('formProveedor').reset();
            document.querySelector('#formProveedor input[name="action"]').value = 'agregar_proveedor';
            document.getElementById('proveedor_id').value = '';
            document.querySelector('#modalProveedor .modal-title').innerHTML = 
                '<i class="fas fa-truck me-2"></i>Nuevo Proveedor';
            document.getElementById('btnSubmitProveedor').innerHTML = 'Guardar Proveedor';
        }
        
        function editarProveedor(id) {
            fetch(`modulo_clientes.php?action=ajax&ajax_action=obtener_proveedor&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const proveedor = data.data;
                        
                        // Llenar formulario de edición
                        document.querySelector('#formProveedor input[name="action"]').value = 'editar_proveedor';
                        document.getElementById('proveedor_id').value = id;
                        document.querySelector('#formProveedor input[name="nombre"]').value = proveedor.nombre;
                        document.querySelector('#formProveedor input[name="ruc"]').value = proveedor.ruc || '';
                        document.querySelector('#formProveedor input[name="telefono"]').value = proveedor.telefono || '';
                        document.querySelector('#formProveedor input[name="email"]').value = proveedor.email || '';
                        document.querySelector('#formProveedor input[name="ciudad"]').value = proveedor.ciudad || '';
                        document.querySelector('#formProveedor textarea[name="direccion"]').value = proveedor.direccion || '';
                        
                        // Cambiar título del modal
                        document.querySelector('#modalProveedor .modal-title').innerHTML = 
                            '<i class="fas fa-edit me-2"></i>Editar Proveedor';
                        document.getElementById('btnSubmitProveedor').innerHTML = 'Actualizar Proveedor';
                        
                        const modal = new bootstrap.Modal(document.getElementById('modalProveedor'));
                        modal.show();
                    } else {
                        alert(data.message || 'Error al cargar datos del proveedor');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cargar datos del proveedor');
                });
        }
        
        function eliminarProveedor(id) {
            if (confirm('¿Está seguro de desactivar este proveedor? Esta acción no se puede deshacer.')) {
                const formData = new FormData();
                formData.append('action', 'eliminar_proveedor');
                formData.append('id', id);
                
                fetch('modulo_clientes.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(() => {
                    location.reload();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al desactivar el proveedor');
                });
            }
        }
        
        // Registrar pago de cliente
        function registrarPagoCliente(id) {
            fetch(`modulo_clientes.php?action=ajax&ajax_action=obtener_cliente&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const cliente = data.data;
                        
                        document.getElementById('pago_cliente_id').value = id;
                        document.getElementById('pago_cliente_nombre').value = cliente.nombre;
                        document.getElementById('pago_cliente_deuda').value = 
                            '$' + parseFloat(cliente.saldo_deuda).toFixed(2);
                        const montoInput = document.querySelector('#formPagoCliente input[name="monto"]');
                        montoInput.max = cliente.saldo_deuda;
                        montoInput.value = cliente.saldo_deuda;
                        
                        const modal = new bootstrap.Modal(document.getElementById('modalPagoCliente'));
                        modal.show();
                    } else {
                        alert(data.message || 'Error al cargar datos del cliente');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cargar datos del cliente');
                });
        }
        
        // Registrar pago a proveedor
        function registrarPagoProveedor(id) {
            fetch(`modulo_clientes.php?action=ajax&ajax_action=obtener_proveedor&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const proveedor = data.data;
                        
                        document.getElementById('pago_proveedor_id').value = id;
                        document.getElementById('pago_proveedor_nombre').value = proveedor.nombre;
                        document.getElementById('pago_proveedor_deuda').value = 
                            '$' + parseFloat(proveedor.saldo_deuda).toFixed(2);
                        const montoInput = document.querySelector('#formPagoProveedor input[name="monto"]');
                        montoInput.max = proveedor.saldo_deuda;
                        montoInput.value = proveedor.saldo_deuda;
                        
                        const modal = new bootstrap.Modal(document.getElementById('modalPagoProveedor'));
                        modal.show();
                    } else {
                        alert(data.message || 'Error al cargar datos del proveedor');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cargar datos del proveedor');
                });
        }
        
        // Inicializar cuando esté listo
        document.addEventListener('DOMContentLoaded', function() {
            inicializarGraficoDeudas();
            configurarBusqueda();
            
            // Configurar validación de pago
            document.getElementById('formPagoCliente')?.addEventListener('submit', function(e) {
                const monto = parseFloat(this.monto.value);
                const deuda = parseFloat(document.getElementById('pago_cliente_deuda').value.replace('$', ''));
                
                if (monto > deuda) {
                    e.preventDefault();
                    alert('El monto no puede ser mayor a la deuda actual');
                    return false;
                }
                if (monto <= 0) {
                    e.preventDefault();
                    alert('El monto debe ser mayor a cero');
                    return false;
                }
            });
            
            document.getElementById('formPagoProveedor')?.addEventListener('submit', function(e) {
                const monto = parseFloat(this.monto.value);
                const deuda = parseFloat(document.getElementById('pago_proveedor_deuda').value.replace('$', ''));
                
                if (monto > deuda) {
                    e.preventDefault();
                    alert('El monto no puede ser mayor a la deuda actual');
                    return false;
                }
                if (monto <= 0) {
                    e.preventDefault();
                    alert('El monto debe ser mayor a cero');
                    return false;
                }
            });
        });
    </script>
</body>
</html>