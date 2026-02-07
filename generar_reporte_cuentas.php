<?php
session_start();
require_once 'database.php';
require_once 'funciones.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    die('No autorizado');
}

$tipo = $_GET['tipo'] ?? 'cobrar';
$estado = $_GET['estado'] ?? 'pendiente';
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01');
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

$db = getDB();

// Configurar headers para descarga CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=reporte_cuentas_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');

// Agregar BOM para UTF-8
fwrite($output, "\xEF\xBB\xBF");

if ($tipo === 'cobrar') {
    fputcsv($output, ['REPORTE DE CUENTAS POR COBRAR']);
    fputcsv($output, ['Fecha desde:', $fecha_desde, 'Fecha hasta:', $fecha_hasta]);
    fputcsv($output, ['Generado:', date('Y-m-d H:i:s')]);
    fputcsv($output, ['']);
    fputcsv($output, ['Fecha', 'Cliente', 'Tipo', 'Monto', 'Saldo Anterior', 'Saldo Nuevo', 'Método Pago', 'Referencia', 'Usuario']);
    
    $sql = "SELECT cc.*, c.nombre as cliente_nombre, u.nombre as usuario_nombre, v.codigo_venta
            FROM cuentas_cobrar cc
            JOIN clientes c ON cc.cliente_id = c.id
            JOIN usuarios u ON cc.usuario_id = u.id
            LEFT JOIN ventas v ON cc.venta_id = v.id
            WHERE DATE(cc.fecha_hora) BETWEEN ? AND ?";
    
    if ($estado === 'pendiente') {
        $sql .= " AND cc.tipo IN ('venta', 'abono')";
    } elseif ($estado === 'pagada') {
        $sql .= " AND cc.tipo = 'pago'";
    }
    
    $sql .= " ORDER BY cc.fecha_hora DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$fecha_desde, $fecha_hasta]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['fecha_hora'],
            $row['cliente_nombre'],
            $row['tipo'],
            $row['monto'],
            $row['saldo_anterior'],
            $row['saldo_nuevo'],
            $row['metodo_pago'] ?? '',
            $row['codigo_venta'] ?: ($row['referencia_pago'] ?? ''),
            $row['usuario_nombre']
        ]);
    }
} else {
    fputcsv($output, ['REPORTE DE CUENTAS POR PAGAR']);
    fputcsv($output, ['Fecha desde:', $fecha_desde, 'Fecha hasta:', $fecha_hasta]);
    fputcsv($output, ['Generado:', date('Y-m-d H:i:s')]);
    fputcsv($output, ['']);
    fputcsv($output, ['Fecha', 'Proveedor', 'Tipo', 'Monto', 'Saldo Anterior', 'Saldo Nuevo', 'Método Pago', 'Referencia', 'Usuario']);
    
    $sql = "SELECT cp.*, p.nombre as proveedor_nombre, u.nombre as usuario_nombre
            FROM cuentas_pagar cp
            JOIN proveedores p ON cp.proveedor_id = p.id
            JOIN usuarios u ON cp.usuario_id = u.id
            WHERE DATE(cp.fecha_hora) BETWEEN ? AND ?";
    
    if ($estado === 'pendiente') {
        $sql .= " AND cp.tipo IN ('compra', 'abono')";
    } elseif ($estado === 'pagada') {
        $sql .= " AND cp.tipo = 'pago'";
    }
    
    $sql .= " ORDER BY cp.fecha_hora DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$fecha_desde, $fecha_hasta]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['fecha_hora'],
            $row['proveedor_nombre'],
            $row['tipo'],
            $row['monto'],
            $row['saldo_anterior'],
            $row['saldo_nuevo'],
            $row['metodo_pago'] ?? '',
            $row['referencia_pago'] ?? '',
            $row['usuario_nombre']
        ]);
    }
}

fclose($output);
?>