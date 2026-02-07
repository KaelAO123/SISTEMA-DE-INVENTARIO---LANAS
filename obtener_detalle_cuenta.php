<?php
// obtener_detalle_cuenta.php
session_start();
require_once 'database.php';
require_once 'funciones.php';

header('Content-Type: application/json');

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$id = $_GET['id'] ?? 0;
$tipo = $_GET['tipo'] ?? 'cobrar';

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID no válido']);
    exit;
}

$db = getDB();

try {
    if ($tipo === 'cobrar') {
        // Obtener detalle de cuenta por cobrar
        $stmt = $db->prepare("
            SELECT cc.*, c.nombre as cliente_nombre, c.telefono, c.email,
                   u.nombre as usuario_nombre, v.codigo_venta, v.total as venta_total,
                   v.pagado as venta_pagado, v.debe as venta_debe
            FROM cuentas_cobrar cc
            JOIN clientes c ON cc.cliente_id = c.id
            JOIN usuarios u ON cc.usuario_id = u.id
            LEFT JOIN ventas v ON cc.venta_id = v.id
            WHERE cc.id = ?
        ");
        $stmt->execute([$id]);
        $detalle = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$detalle) {
            throw new Exception('Cuenta no encontrada');
        }
        
        // Preparar respuesta
        $respuesta = [
            'success' => true,
            'detalle' => [
                'id' => $detalle['id'],
                'tipo' => 'cobrar',
                'cliente_nombre' => htmlspecialchars($detalle['cliente_nombre']),
                'telefono' => $detalle['telefono'],
                'fecha_hora_formateada' => Funciones::formatearFecha($detalle['fecha_hora'], 'd/m/Y H:i:s'),
                'monto_formateado' => Funciones::formatearMoneda($detalle['monto']),
                'saldo_anterior_formateado' => Funciones::formatearMoneda($detalle['saldo_anterior']),
                'saldo_nuevo_formateado' => Funciones::formatearMoneda($detalle['saldo_nuevo']),
                'diferencia' => $detalle['saldo_anterior'] - $detalle['saldo_nuevo'],
                'diferencia_formateado' => Funciones::formatearMoneda($detalle['saldo_anterior'] - $detalle['saldo_nuevo']),
                'metodo_pago' => $detalle['metodo_pago'],
                'referencia_pago' => $detalle['referencia_pago'],
                'usuario_nombre' => htmlspecialchars($detalle['usuario_nombre']),
                'observaciones' => $detalle['observaciones']
            ]
        ];
        
        // Agregar información de venta si existe
        if ($detalle['codigo_venta']) {
            $respuesta['detalle']['venta_info'] = [
                'codigo' => $detalle['codigo_venta'],
                'total' => Funciones::formatearMoneda($detalle['venta_total']),
                'pagado' => Funciones::formatearMoneda($detalle['venta_pagado']),
                'debe' => Funciones::formatearMoneda($detalle['venta_debe'])
            ];
        }
        
    } else {
        // Obtener detalle de cuenta por pagar
        $stmt = $db->prepare("
            SELECT cp.*, p.nombre as proveedor_nombre, p.telefono, p.email,
                   u.nombre as usuario_nombre
            FROM cuentas_pagar cp
            JOIN proveedores p ON cp.proveedor_id = p.id
            JOIN usuarios u ON cp.usuario_id = u.id
            WHERE cp.id = ?
        ");
        $stmt->execute([$id]);
        $detalle = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$detalle) {
            throw new Exception('Cuenta no encontrada');
        }
        
        // Preparar respuesta
        $respuesta = [
            'success' => true,
            'detalle' => [
                'id' => $detalle['id'],
                'tipo' => 'pagar',
                'proveedor_nombre' => htmlspecialchars($detalle['proveedor_nombre']),
                'telefono' => $detalle['telefono'],
                'fecha_hora_formateada' => Funciones::formatearFecha($detalle['fecha_hora'], 'd/m/Y H:i:s'),
                'monto_formateado' => Funciones::formatearMoneda($detalle['monto']),
                'saldo_anterior_formateado' => Funciones::formatearMoneda($detalle['saldo_anterior']),
                'saldo_nuevo_formateado' => Funciones::formatearMoneda($detalle['saldo_nuevo']),
                'diferencia' => $detalle['saldo_anterior'] - $detalle['saldo_nuevo'],
                'diferencia_formateado' => Funciones::formatearMoneda($detalle['saldo_anterior'] - $detalle['saldo_nuevo']),
                'metodo_pago' => $detalle['metodo_pago'],
                'referencia_pago' => $detalle['referencia_pago'],
                'usuario_nombre' => htmlspecialchars($detalle['usuario_nombre']),
                'observaciones' => $detalle['observaciones']
            ]
        ];
    }
    
    echo json_encode($respuesta);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>