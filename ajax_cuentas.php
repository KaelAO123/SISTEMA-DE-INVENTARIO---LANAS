<?php
require_once 'database.php';
require_once 'funciones.php';

Funciones::verificarSesion();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'get_ventas_pendientes') {
    getVentasPendientes();
} elseif ($action === 'get_compras_pendientes') {
    getComprasPendientes();
} elseif ($action === 'renovar_vencimiento') {
    renovarVencimiento();
}

function getVentasPendientes() {
    $cliente_id = $_GET['cliente_id'];
    $db = getDB();
    
    $sql = "SELECT v.*, 
           (v.debe - COALESCE((
               SELECT SUM(cc.monto) 
               FROM cuentas_cobrar cc 
               WHERE cc.venta_id = v.id 
               AND cc.tipo = 'pago'
           ), 0)) as saldo_pendiente
           FROM ventas v
           WHERE v.cliente_id = ?
           AND v.debe > 0
           AND v.anulado = FALSE
           ORDER BY v.fecha_hora DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$cliente_id]);
    $ventas = $stmt->fetchAll();
    
    echo json_encode($ventas);
}

function getComprasPendientes() {
    $proveedor_id = $_GET['proveedor_id'];
    $db = getDB();
    
    $sql = "SELECT cp.*, 
           (cp.saldo_pendiente - COALESCE((
               SELECT SUM(cp2.monto) 
               FROM cuentas_pagar cp2 
               WHERE cp2.proveedor_id = cp.proveedor_id 
               AND cp2.tipo = 'pago'
               AND DATE(cp2.fecha_hora) <= CURDATE()
           ), 0)) as saldo_actual
           FROM compras_proveedores cp
           WHERE cp.proveedor_id = ?
           AND cp.saldo_pendiente > 0
           ORDER BY cp.fecha_compra DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$proveedor_id]);
    $compras = $stmt->fetchAll();
    
    echo json_encode($compras);
}

function renovarVencimiento() {
    $venta_id = $_POST['venta_id'];
    $fecha = $_POST['fecha'];
    
    $db = getDB();
    
    try {
        $stmt = $db->prepare("UPDATE ventas SET fecha_vencimiento = ? WHERE id = ?");
        $stmt->execute([$fecha, $venta_id]);
        
        echo json_encode(['success' => true, 'message' => 'Vencimiento actualizado']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>