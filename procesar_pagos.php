<?php
// procesar_pagos.php
session_start();

// Configurar headers para JSON
header('Content-Type: application/json; charset=utf-8');

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado. Por favor inicie sesión.']);
    exit;
}

// Incluir archivos necesarios
require_once 'database.php';
require_once 'funciones.php';

// Obtener conexión a la base de datos
try {
    $db = getDB();
    if (!$db) {
        throw new Exception('No se pudo conectar a la base de datos');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos: ' . $e->getMessage()]);
    exit;
}

try {
    // Verificar que sea una solicitud POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido. Use POST.');
    }
    
    // Obtener acción
    $action = $_POST['action'] ?? '';
    
    if (empty($action)) {
        throw new Exception('Acción no especificada');
    }
    
    // Validar usuario
    if (empty($_POST['usuario_id'])) {
        throw new Exception('Usuario no válido');
    }
    
    $usuario_id = intval($_POST['usuario_id']);
    
    // Procesar según la acción
    if ($action === 'registrar_pago_cliente') {
        $response = registrarPagoCliente($db, $usuario_id);
    } elseif ($action === 'registrar_pago_proveedor') {
        $response = registrarPagoProveedor($db, $usuario_id);
    } else {
        throw new Exception('Acción no válida: ' . $action);
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log('Error PDO en procesar_pagos.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error en la base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log('Error en procesar_pagos.php: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}

function registrarPagoCliente($db, $usuario_id) {
    // Validar campos requeridos
    $required = ['cliente_id', 'monto', 'metodo_pago'];
    foreach ($required as $field) {
        if (!isset($_POST[$field]) || $_POST[$field] === '') {
            throw new Exception("Campo requerido: $field");
        }
    }
    
    // Obtener y validar datos
    $cliente_id = intval($_POST['cliente_id']);
    $monto = floatval($_POST['monto']);
    $metodo_pago = trim($_POST['metodo_pago']);
    $referencia_pago = trim($_POST['referencia_pago'] ?? '');
    $observaciones = trim($_POST['observaciones'] ?? '');
    
    // Validaciones básicas
    if ($cliente_id <= 0) {
        throw new Exception('ID de cliente inválido');
    }
    
    if ($monto <= 0) {
        throw new Exception('El monto debe ser mayor a 0');
    }
    
    if ($usuario_id <= 0) {
        throw new Exception('Usuario no válido');
    }
    
    // Validar métodos de pago permitidos
    $metodos_permitidos = ['efectivo', 'transferencia', 'tarjeta', 'cheque'];
    if (!in_array($metodo_pago, $metodos_permitidos)) {
        throw new Exception('Método de pago no válido');
    }
    
    // Obtener saldo actual del cliente
    $stmt = $db->prepare("SELECT id, nombre, saldo_deuda FROM clientes WHERE id = ? AND activo = TRUE");
    $stmt->execute([$cliente_id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente) {
        throw new Exception('Cliente no encontrado o inactivo');
    }
    
    $saldo_anterior = floatval($cliente['saldo_deuda']);
    
    if ($monto > $saldo_anterior) {
        throw new Exception('El monto a pagar ($' . number_format($monto, 2) . ') no puede ser mayor a la deuda actual ($' . number_format($saldo_anterior, 2) . ')');
    }
    
    $saldo_nuevo = $saldo_anterior - $monto;
    
    // Iniciar transacción
    $db->beginTransaction();
    
    try {
        // Registrar en cuentas por cobrar
        $stmt = $db->prepare("
            INSERT INTO cuentas_cobrar 
            (cliente_id, venta_id, tipo, monto, saldo_anterior, saldo_nuevo, metodo_pago, 
             referencia_pago, fecha_hora, usuario_id, observaciones)
            VALUES (?, NULL, 'pago', ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        
        $stmt->execute([
            $cliente_id, 
            $monto, 
            $saldo_anterior, 
            $saldo_nuevo, 
            $metodo_pago,
            $referencia_pago, 
            $usuario_id, 
            $observaciones
        ]);
        
        // Actualizar saldo del cliente
        $stmt = $db->prepare("UPDATE clientes SET saldo_deuda = ? WHERE id = ?");
        $stmt->execute([$saldo_nuevo, $cliente_id]);
        
        // Buscar ventas pendientes para aplicar el pago
        $stmt = $db->prepare("
            SELECT id, debe 
            FROM ventas 
            WHERE cliente_id = ? AND debe > 0 AND anulado = 0
            ORDER BY fecha_hora ASC
        ");
        $stmt->execute([$cliente_id]);
        $ventas_pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $monto_restante = $monto;
        foreach ($ventas_pendientes as $venta) {
            if ($monto_restante <= 0) break;
            
            $deuda_venta = floatval($venta['debe']);
            $pago_a_aplicar = min($monto_restante, $deuda_venta);
            
            // Aplicar pago a la venta
            $stmt = $db->prepare("
                UPDATE ventas 
                SET debe = debe - ?, 
                    pagado = pagado + ?
                WHERE id = ?
            ");
            $stmt->execute([$pago_a_aplicar, $pago_a_aplicar, $venta['id']]);
            
            // Actualizar estado si está pagada completamente
            $stmt = $db->prepare("
                UPDATE ventas 
                SET estado = CASE WHEN (debe - ?) <= 0 THEN 'pagada' ELSE estado END
                WHERE id = ?
            ");
            $stmt->execute([$pago_a_aplicar, $venta['id']]);
            
            $monto_restante -= $pago_a_aplicar;
        }
        
        // Confirmar transacción
        $db->commit();
        
        return [
            'success' => true,
            'message' => 'Pago registrado exitosamente',
            'data' => [
                'cliente' => $cliente['nombre'],
                'monto' => $monto,
                'saldo_anterior' => $saldo_anterior,
                'saldo_nuevo' => $saldo_nuevo,
                'fecha' => date('Y-m-d H:i:s')
            ]
        ];
        
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $db->rollBack();
        throw new Exception('Error al procesar el pago: ' . $e->getMessage());
    }
}

function registrarPagoProveedor($db, $usuario_id) {
    // Validar campos requeridos
    $required = ['proveedor_id', 'monto', 'metodo_pago'];
    foreach ($required as $field) {
        if (!isset($_POST[$field]) || $_POST[$field] === '') {
            throw new Exception("Campo requerido: $field");
        }
    }
    
    // Obtener y validar datos
    $proveedor_id = intval($_POST['proveedor_id']);
    $monto = floatval($_POST['monto']);
    $metodo_pago = trim($_POST['metodo_pago']);
    $referencia_pago = trim($_POST['referencia_pago'] ?? '');
    $observaciones = trim($_POST['observaciones'] ?? '');
    
    // Validaciones básicas
    if ($proveedor_id <= 0) {
        throw new Exception('ID de proveedor inválido');
    }
    
    if ($monto <= 0) {
        throw new Exception('El monto debe ser mayor a 0');
    }
    
    if ($usuario_id <= 0) {
        throw new Exception('Usuario no válido');
    }
    
    // Validar métodos de pago permitidos
    $metodos_permitidos = ['efectivo', 'transferencia', 'cheque'];
    if (!in_array($metodo_pago, $metodos_permitidos)) {
        throw new Exception('Método de pago no válido');
    }
    
    // Obtener saldo actual del proveedor
    $stmt = $db->prepare("SELECT id, nombre, saldo_deuda FROM proveedores WHERE id = ? AND activo = TRUE");
    $stmt->execute([$proveedor_id]);
    $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$proveedor) {
        throw new Exception('Proveedor no encontrado o inactivo');
    }
    
    $saldo_anterior = floatval($proveedor['saldo_deuda']);
    
    if ($monto > $saldo_anterior) {
        throw new Exception('El monto a pagar ($' . number_format($monto, 2) . ') no puede ser mayor a la deuda actual ($' . number_format($saldo_anterior, 2) . ')');
    }
    
    $saldo_nuevo = $saldo_anterior - $monto;
    
    // Iniciar transacción
    $db->beginTransaction();
    
    try {
        // Registrar en cuentas por pagar
        $stmt = $db->prepare("
            INSERT INTO cuentas_pagar 
            (proveedor_id, tipo, monto, saldo_anterior, saldo_nuevo, metodo_pago, 
             referencia_pago, fecha_hora, usuario_id, observaciones)
            VALUES (?, 'pago', ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        
        $stmt->execute([
            $proveedor_id, 
            $monto, 
            $saldo_anterior, 
            $saldo_nuevo, 
            $metodo_pago,
            $referencia_pago, 
            $usuario_id, 
            $observaciones
        ]);
        
        // Actualizar saldo del proveedor
        $stmt = $db->prepare("UPDATE proveedores SET saldo_deuda = ? WHERE id = ?");
        $stmt->execute([$saldo_nuevo, $proveedor_id]);
        
        // Buscar compras pendientes para aplicar el pago
        $stmt = $db->prepare("
            SELECT id, saldo_pendiente 
            FROM compras_proveedores 
            WHERE proveedor_id = ? AND saldo_pendiente > 0
            ORDER BY fecha_compra ASC
        ");
        $stmt->execute([$proveedor_id]);
        $compras_pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $monto_restante = $monto;
        foreach ($compras_pendientes as $compra) {
            if ($monto_restante <= 0) break;
            
            $deuda_compra = floatval($compra['saldo_pendiente']);
            $pago_a_aplicar = min($monto_restante, $deuda_compra);
            
            // Aplicar pago a la compra
            $stmt = $db->prepare("
                UPDATE compras_proveedores 
                SET saldo_pendiente = saldo_pendiente - ?
                WHERE id = ?
            ");
            $stmt->execute([$pago_a_aplicar, $compra['id']]);
            
            // Actualizar estado
            $stmt = $db->prepare("
                UPDATE compras_proveedores 
                SET estado = CASE 
                    WHEN (saldo_pendiente - ?) <= 0 THEN 'pagada'
                    ELSE 'parcial'
                END
                WHERE id = ?
            ");
            $stmt->execute([$pago_a_aplicar, $compra['id']]);
            
            $monto_restante -= $pago_a_aplicar;
        }
        
        // Confirmar transacción
        $db->commit();
        
        return [
            'success' => true,
            'message' => 'Pago registrado exitosamente',
            'data' => [
                'proveedor' => $proveedor['nombre'],
                'monto' => $monto,
                'saldo_anterior' => $saldo_anterior,
                'saldo_nuevo' => $saldo_nuevo,
                'fecha' => date('Y-m-d H:i:s')
            ]
        ];
        
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $db->rollBack();
        throw new Exception('Error al procesar el pago: ' . $e->getMessage());
    }
}
?>