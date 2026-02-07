<?php

require_once 'database.php';
require_once 'funciones.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
Funciones::verificarSesion();


header('Content-Type: application/json; charset=utf-8');

$db = getDB();
$response = ['success' => false, 'message' => '', 'data' => null];

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'obtener_usuario':
                $id = intval($_GET['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception("ID de usuario inválido");
                }
                
                $stmt = $db->prepare("SELECT id, username, nombre, email, rol, estado, ultimo_login, creado_en FROM usuarios WHERE id = ?");
                $stmt->execute([$id]);
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($usuario) {
                    $response['success'] = true;
                    $response['data'] = $usuario;
                } else {
                    throw new Exception("Usuario no encontrado");
                }
                break;
                
            case 'obtener_paquete':
                $id = intval($_GET['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception("ID de paquete inválido");
                }
                
                $stmt = $db->prepare("SELECT * FROM paquetes WHERE id = ?");
                $stmt->execute([$id]);
                $paquete = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($paquete) {
                    $response['success'] = true;
                    $response['data'] = $paquete;
                } else {
                    throw new Exception("Paquete no encontrado");
                }
                break;
                
            case 'obtener_subpaquete':
                $id = intval($_GET['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception("ID de subpaquete inválido");
                }
                
                $stmt = $db->prepare("SELECT * FROM subpaquetes WHERE id = ?");
                $stmt->execute([$id]);
                $subpaquete = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($subpaquete) {
                    $response['success'] = true;
                    $response['data'] = $subpaquete;
                } else {
                    throw new Exception("Subpaquete no encontrado");
                }
                break;
                
            case 'buscar_productos':
                $busqueda = $_GET['q'] ?? '';
                $stmt = $db->prepare("SELECT sp.*, p.nombre as paquete_nombre 
                                     FROM subpaquetes sp 
                                     JOIN paquetes p ON sp.paquete_id = p.id 
                                     WHERE (sp.nombre_color LIKE ? OR sp.codigo_color LIKE ? OR p.nombre LIKE ?)
                                     AND sp.activo = TRUE
                                     ORDER BY sp.nombre_color 
                                     LIMIT 20");
                $like = "%$busqueda%";
                $stmt->execute([$like, $like, $like]);
                $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $response['success'] = true;
                $response['data'] = $productos;
                break;
                
            case 'obtener_venta':
                $id = intval($_GET['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception("ID de venta inválido");
                }
                
                $stmt = $db->prepare("SELECT v.*, c.nombre as cliente_nombre 
                                     FROM ventas v 
                                     LEFT JOIN clientes c ON v.cliente_id = c.id 
                                     WHERE v.id = ?");
                $stmt->execute([$id]);
                $venta = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($venta) {
                    // Obtener detalles
                    $stmt = $db->prepare("SELECT vd.*, sp.nombre_color, sp.codigo_color 
                                         FROM venta_detalles vd 
                                         JOIN subpaquetes sp ON vd.subpaquete_id = sp.id 
                                         WHERE vd.venta_id = ?");
                    $stmt->execute([$id]);
                    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $response['success'] = true;
                    $response['data'] = [
                        'venta' => $venta,
                        'detalles' => $detalles
                    ];
                } else {
                    throw new Exception('Venta no encontrada');
                }
                break;
                
            default:
                throw new Exception("Acción GET no válida: $action");
        }
    } 
    elseif ($method === 'POST') {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'anular_venta':
                if (!Funciones::esAdmin()) {
                    throw new Exception('No tiene permisos para esta acción');
                }
                
                $id = intval($_POST['id'] ?? 0);
                $motivo = Funciones::sanitizar($_POST['motivo'] ?? '');
                
                if ($id <= 0) {
                    throw new Exception('ID de venta inválido');
                }
                
                if (empty($motivo)) {
                    throw new Exception('Debe especificar un motivo para la anulación');
                }
                
                $db->beginTransaction();
                
                // Obtener venta
                $stmt = $db->prepare("SELECT * FROM ventas WHERE id = ? AND anulado = FALSE");
                $stmt->execute([$id]);
                $venta = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$venta) {
                    throw new Exception('Venta no encontrada o ya anulada');
                }
                
                // Obtener detalles para reversar stock
                $stmt = $db->prepare("SELECT * FROM venta_detalles WHERE venta_id = ?");
                $stmt->execute([$id]);
                $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Reversar stock
                foreach ($detalles as $detalle) {
                    $stmt = $db->prepare("UPDATE subpaquetes SET stock = stock + ? WHERE id = ?");
                    $stmt->execute([$detalle['cantidad'], $detalle['subpaquete_id']]);
                    
                    // Registrar movimiento de reverso
                    $stmt = $db->prepare("INSERT INTO movimientos_stock 
                                         (subpaquete_id, tipo, cantidad, stock_anterior, stock_nuevo,
                                          referencia, usuario_id, fecha_hora, observaciones)
                                         SELECT id, 'devolucion', ?, stock - ?, stock, 
                                                ?, ?, NOW(), ?
                                         FROM subpaquetes WHERE id = ?");
                    $stmt->execute([
                        $detalle['cantidad'],
                        $detalle['cantidad'],
                        $venta['codigo_venta'],
                        Funciones::obtenerUsuarioId(),
                        "Anulación: $motivo",
                        $detalle['subpaquete_id']
                    ]);
                }
                
                // Marcar venta como anulada
                $stmt = $db->prepare("UPDATE ventas SET anulado = TRUE, motivo_anulacion = ? WHERE id = ?");
                $stmt->execute([$motivo, $id]);
                
                // Reversar deuda si era crédito
                if ($venta['cliente_id'] && $venta['debe'] > 0) {
                    $stmt = $db->prepare("UPDATE clientes SET saldo_deuda = saldo_deuda - ? WHERE id = ?");
                    $stmt->execute([$venta['debe'], $venta['cliente_id']]);
                    
                    // Registrar en cuentas por cobrar
                    $stmt = $db->prepare("INSERT INTO cuentas_cobrar 
                                         (cliente_id, venta_id, tipo, monto, saldo_anterior, saldo_nuevo,
                                          fecha_hora, usuario_id, observaciones)
                                         SELECT ?, ?, 'abono', ?, saldo_deuda + ?, saldo_deuda, 
                                                NOW(), ?, ?
                                         FROM clientes WHERE id = ?");
                    $stmt->execute([
                        $venta['cliente_id'],
                        $id,
                        $venta['debe'],
                        $venta['debe'],
                        Funciones::obtenerUsuarioId(),
                        "Anulación venta #{$venta['codigo_venta']}: $motivo",
                        $venta['cliente_id']
                    ]);
                }
                
                $db->commit();
                $response['success'] = true;
                $response['message'] = 'Venta anulada exitosamente';
                break;
                
            case 'registrar_pago_cliente':
                $cliente_id = intval($_POST['cliente_id'] ?? 0);
                $monto = floatval($_POST['monto'] ?? 0);
                $metodo = $_POST['metodo'] ?? 'efectivo';
                $referencia = Funciones::sanitizar($_POST['referencia'] ?? '');
                
                if ($cliente_id <= 0) {
                    throw new Exception('Cliente inválido');
                }
                
                if ($monto <= 0) {
                    throw new Exception('Monto inválido');
                }
                
                $db->beginTransaction();
                
                // Obtener saldo actual
                $stmt = $db->prepare("SELECT saldo_deuda FROM clientes WHERE id = ?");
                $stmt->execute([$cliente_id]);
                $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$cliente) {
                    throw new Exception('Cliente no encontrado');
                }
                
                if ($cliente['saldo_deuda'] < $monto) {
                    throw new Exception('El monto excede la deuda del cliente');
                }
                
                // Actualizar saldo
                $nuevo_saldo = $cliente['saldo_deuda'] - $monto;
                $stmt = $db->prepare("UPDATE clientes SET saldo_deuda = ? WHERE id = ?");
                $stmt->execute([$nuevo_saldo, $cliente_id]);
                
                // Registrar pago
                $stmt = $db->prepare("INSERT INTO cuentas_cobrar 
                                     (cliente_id, tipo, monto, saldo_anterior, saldo_nuevo,
                                      metodo_pago, referencia_pago, fecha_hora, usuario_id)
                                     VALUES (?, 'pago', ?, ?, ?, ?, ?, NOW(), ?)");
                $stmt->execute([
                    $cliente_id,
                    $monto,
                    $cliente['saldo_deuda'],
                    $nuevo_saldo,
                    $metodo,
                    $referencia,
                    Funciones::obtenerUsuarioId()
                ]);
                
                $db->commit();
                $response['success'] = true;
                $response['message'] = 'Pago registrado exitosamente';
                break;
                
            case 'registrar_pago_proveedor':
                $proveedor_id = intval($_POST['proveedor_id'] ?? 0);
                $monto = floatval($_POST['monto'] ?? 0);
                $metodo = $_POST['metodo'] ?? 'transferencia';
                $referencia = Funciones::sanitizar($_POST['referencia'] ?? '');
                
                if ($proveedor_id <= 0) {
                    throw new Exception('Proveedor inválido');
                }
                
                if ($monto <= 0) {
                    throw new Exception('Monto inválido');
                }
                
                $db->beginTransaction();
                
                // Obtener saldo actual
                $stmt = $db->prepare("SELECT saldo_deuda FROM proveedores WHERE id = ?");
                $stmt->execute([$proveedor_id]);
                $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$proveedor) {
                    throw new Exception('Proveedor no encontrado');
                }
                
                if ($proveedor['saldo_deuda'] < $monto) {
                    throw new Exception('El monto excede la deuda del proveedor');
                }
                
                // Actualizar saldo
                $nuevo_saldo = $proveedor['saldo_deuda'] - $monto;
                $stmt = $db->prepare("UPDATE proveedores SET saldo_deuda = ? WHERE id = ?");
                $stmt->execute([$nuevo_saldo, $proveedor_id]);
                
                // Registrar pago
                $stmt = $db->prepare("INSERT INTO cuentas_pagar 
                                     (proveedor_id, tipo, monto, saldo_anterior, saldo_nuevo,
                                      metodo_pago, referencia_pago, fecha_hora, usuario_id)
                                     VALUES (?, 'pago', ?, ?, ?, ?, ?, NOW(), ?)");
                $stmt->execute([
                    $proveedor_id,
                    $monto,
                    $proveedor['saldo_deuda'],
                    $nuevo_saldo,
                    $metodo,
                    $referencia,
                    Funciones::obtenerUsuarioId()
                ]);
                
                $db->commit();
                $response['success'] = true;
                $response['message'] = 'Pago registrado exitosamente';
                break;
                
            case 'marcar_notificacion_leida':
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception('ID de notificación inválido');
                }
                
                $stmt = $db->prepare("UPDATE notificaciones SET leida = TRUE WHERE id = ?");
                $stmt->execute([$id]);
                $response['success'] = true;
                $response['message'] = 'Notificación marcada como leída';
                break;
                
            case 'reponer_stock':
                $subpaquete_id = intval($_POST['subpaquete_id'] ?? 0);
                $cantidad = intval($_POST['cantidad'] ?? 0);
                $observacion = Funciones::sanitizar($_POST['observacion'] ?? '');
                
                if ($subpaquete_id <= 0) {
                    throw new Exception('Producto inválido');
                }
                
                if ($cantidad <= 0) {
                    throw new Exception('Cantidad inválida');
                }
                
                $db->beginTransaction();
                
                // Obtener stock actual
                $stmt = $db->prepare("SELECT stock FROM subpaquetes WHERE id = ?");
                $stmt->execute([$subpaquete_id]);
                $producto = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$producto) {
                    throw new Exception('Producto no encontrado');
                }
                
                // Actualizar stock
                $nuevo_stock = $producto['stock'] + $cantidad;
                $stmt = $db->prepare("UPDATE subpaquetes SET stock = ? WHERE id = ?");
                $stmt->execute([$nuevo_stock, $subpaquete_id]);
                
                // Registrar movimiento
                $stmt = $db->prepare("INSERT INTO movimientos_stock 
                                     (subpaquete_id, tipo, cantidad, stock_anterior, stock_nuevo,
                                      usuario_id, fecha_hora, observaciones)
                                     VALUES (?, 'ingreso', ?, ?, ?, ?, NOW(), ?)");
                $stmt->execute([
                    $subpaquete_id,
                    $cantidad,
                    $producto['stock'],
                    $nuevo_stock,
                    Funciones::obtenerUsuarioId(),
                    $observacion ?: 'Reposición manual'
                ]);
                
                $db->commit();
                $response['success'] = true;
                $response['message'] = 'Stock repuesto exitosamente';
                break;
                
            default:
                throw new Exception("Acción POST no válida: $action");
        }
    } 
    else {
        throw new Exception("Método HTTP no permitido: $method");
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("Error en AJAX (" . ($_GET['action'] ?? $_POST['action'] ?? 'unknown') . "): " . $e->getMessage());
} catch (PDOException $e) {
    $response['message'] = "Error de base de datos: " . $e->getMessage();
    error_log("PDO Error en AJAX: " . $e->getMessage());
}


ob_clean();

// Enviar respuesta JSON
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit();
?>