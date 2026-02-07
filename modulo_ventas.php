<?php
require_once 'database.php';
require_once 'funciones.php';

Funciones::verificarSesion();

$db = getDB();
$error = '';
$success = '';

// Procesar venta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'procesar_venta') {
        try {
            $cliente_id = isset($_POST['cliente_id']) && $_POST['cliente_id'] > 0 ? intval($_POST['cliente_id']) : null;
            $tipo_pago = $_POST['tipo_pago'] ?? 'contado';
            $observaciones = Funciones::sanitizar($_POST['observaciones'] ?? '');
            $descuento = floatval($_POST['descuento'] ?? 0);
            $productos_json = $_POST['productos'] ?? '[]';
            $productos = json_decode($productos_json, true);
            
            if (empty($productos)) {
                throw new Exception("No hay productos en el carrito");
            }
            
            $db->beginTransaction();
            
            // 1. Validar stock y calcular total
            $subtotal = 0;
            $detalles = [];
            
            foreach ($productos as $producto) {
                $stmt = $db->prepare("SELECT precio_venta, stock, nombre_color, codigo_color 
                                     FROM subpaquetes WHERE id = ? AND activo = 1");
                $stmt->execute([$producto['id']]);
                $info = $stmt->fetch();
                
                if (!$info) {
                    throw new Exception("Producto no encontrado o inactivo");
                }
                
                if ($info['stock'] < $producto['cantidad']) {
                    throw new Exception("Stock insuficiente: {$info['nombre_color']} (Disponible: {$info['stock']})");
                }
                
                $precio = $info['precio_venta'];
                $total_item = $precio * $producto['cantidad'];
                $subtotal += $total_item;
                
                $detalles[] = [
                    'id' => $producto['id'],
                    'cantidad' => $producto['cantidad'],
                    'precio' => $precio,
                    'total' => $total_item,
                    'nombre' => $info['nombre_color'],
                    'codigo' => $info['codigo_color'],
                    'stock_anterior' => $info['stock']
                ];
            }
            
            // 2. Calcular totales
            if ($descuento > $subtotal) {
                $descuento = $subtotal;
            }
            
            $subtotal_neto = $subtotal - $descuento;
            $iva = $subtotal_neto * 0.12;
            $total = $subtotal_neto + $iva;
            
            // 3. Generar código único
            $codigo_venta = 'V-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Verificar que el código no exista
            $stmt = $db->prepare("SELECT COUNT(*) FROM ventas WHERE codigo_venta = ?");
            $stmt->execute([$codigo_venta]);
            if ($stmt->fetchColumn() > 0) {
                $codigo_venta .= '-' . time();
            }
            
            // 4. Determinar pagado y debe
            $pagado = ($tipo_pago === 'contado') ? $total : 0;
            $debe = $total - $pagado;
            $estado = ($tipo_pago === 'contado') ? 'pagada' : 'pendiente';
            
            // 5. Insertar venta
            $stmt = $db->prepare("INSERT INTO ventas 
                (codigo_venta, cliente_id, vendedor_id, subtotal, descuento, iva, total,
                 pagado, debe, tipo_pago, estado, fecha_hora, observaciones, anulado)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, FALSE)");
            
            $stmt->execute([
                $codigo_venta,
                $cliente_id,
                $_SESSION['usuario_id'],
                $subtotal_neto,
                $descuento,
                $iva,
                $total,
                $pagado,
                $debe,
                $tipo_pago,
                $estado,
                $observaciones
            ]);
            
            $venta_id = $db->lastInsertId();
            
            // 
            foreach ($detalles as $detalle) {
                // 1. Verificar que el subpaquete existe y está activo
                $stmt = $db->prepare("SELECT id, stock FROM subpaquetes WHERE id = ? AND activo = 1");
                $stmt->execute([$detalle['id']]);
                $subpaquete = $stmt->fetch();
                
                if (!$subpaquete) {
                    throw new Exception("Producto con ID {$detalle['id']} no existe o está inactivo");
                }
                
                // 2. Validar stock disponible (opcional pero recomendado)
                $stock_anterior = $subpaquete['stock'];
                if ($detalle['cantidad'] > $stock_anterior) {
                    throw new Exception("Stock insuficiente para el producto ID {$detalle['id']}. Disponible: {$stock_anterior}, Solicitado: {$detalle['cantidad']}");
                }
                
                // 3. Insertar detalle de venta
                $stmt = $db->prepare("INSERT INTO venta_detalles 
                    (venta_id, subpaquete_id, cantidad, precio_unitario, subtotal, hora_extraccion)
                    VALUES (?, ?, ?, ?, ?, CURTIME())");
                $stmt->execute([
                    $venta_id,
                    $detalle['id'],
                    $detalle['cantidad'],
                    $detalle['precio'],
                    $detalle['total']
                ]);
                
                // 4. Calcular nuevo stock
                $nuevo_stock = $stock_anterior - $detalle['cantidad'];
                
                // 5. Actualizar stock del subpaquete
                $stmt = $db->prepare("UPDATE subpaquetes 
                                    SET stock = ?, 
                                        vendido_total = COALESCE(vendido_total, 0) + ?,
                                        fecha_ultima_venta = CURDATE()
                                    WHERE id = ?");
                $stmt->execute([
                    $nuevo_stock, 
                    $detalle['cantidad'], 
                    $detalle['id']
                ]);
                
                // 6. Registrar movimiento de stock
                $stmt = $db->prepare("INSERT INTO movimientos_stock 
                    (subpaquete_id, tipo, cantidad, stock_anterior, stock_nuevo, usuario_id, fecha_hora, observaciones)
                    VALUES (?, 'venta', ?, ?, ?, ?, NOW(), ?)");
                $stmt->execute([
                    $detalle['id'],
                    $detalle['cantidad'],
                    $stock_anterior,
                    $nuevo_stock,
                    $_SESSION['usuario_id'],
                    "Venta #{$codigo_venta}"
                ]);
                
            }
            
            // 7. Actualizar cliente si es crédito
            if ($tipo_pago === 'credito' && $cliente_id > 0) {
                $stmt = $db->prepare("UPDATE clientes 
                                     SET saldo_deuda = COALESCE(saldo_deuda, 0) + ?, 
                                         historial_compras = COALESCE(historial_compras, 0) + 1,
                                         total_comprado = COALESCE(total_comprado, 0) + ?
                                     WHERE id = ?");
                $stmt->execute([$debe, $total, $cliente_id]);
                
                // Registrar en cuentas por cobrar
                $stmt = $db->prepare("SELECT saldo_deuda FROM clientes WHERE id = ?");
                $stmt->execute([$cliente_id]);
                $saldo_actual = $stmt->fetchColumn();
                $saldo_anterior = $saldo_actual - $debe;
                
                $stmt = $db->prepare("INSERT INTO cuentas_cobrar 
                    (cliente_id, tipo, monto, saldo_anterior, saldo_nuevo, fecha_hora, usuario_id)
                    VALUES (?, 'venta', ?, ?, ?, NOW(), ?)");
                $stmt->execute([
                    $cliente_id,
                    $debe,
                    $saldo_anterior,
                    $saldo_actual,
                    $_SESSION['usuario_id']
                ]);
            } elseif ($cliente_id > 0) {
                // Actualizar historial aunque sea contado
                $stmt = $db->prepare("UPDATE clientes 
                                     SET historial_compras = COALESCE(historial_compras, 0) + 1,
                                         total_comprado = COALESCE(total_comprado, 0) + ?
                                     WHERE id = ?");
                $stmt->execute([$total, $cliente_id]);
            }
            
            $db->commit();
            
            // Guardar ID de venta para redirección
            $_SESSION['venta_procesada_id'] = $venta_id;
            $_SESSION['venta_procesada_codigo'] = $codigo_venta;
            
            // Responder con JSON
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'venta_id' => $venta_id,
                'codigo_venta' => $codigo_venta,
                'redirect' => 'imprimir_recibo.php?id=' . $venta_id
            ]);
            exit();
            
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit();
        }
    }
    
    // Guardar nuevo cliente
    if ($_POST['accion'] === 'guardar_cliente') {
        try {
            $nombre = Funciones::sanitizar($_POST['nombre']);
            $telefono = Funciones::sanitizar($_POST['telefono'] ?? '');
            $email = Funciones::sanitizar($_POST['email'] ?? '');
            $limite_credito = floatval($_POST['limite_credito'] ?? 500);
            
            if (empty($nombre)) {
                throw new Exception("El nombre es obligatorio");
            }
            
            $stmt = $db->prepare("INSERT INTO clientes 
                (nombre, telefono, email, limite_credito, saldo_deuda, activo, creado_en)
                VALUES (?, ?, ?, ?, 0, TRUE, NOW())");
            
            $stmt->execute([$nombre, $telefono, $email, $limite_credito]);
            $cliente_id = $db->lastInsertId();
            
            // Obtener info del cliente
            $stmt = $db->prepare("SELECT id, nombre, telefono, email, saldo_deuda, limite_credito FROM clientes WHERE id = ?");
            $stmt->execute([$cliente_id]);
            $cliente = $stmt->fetch();
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'cliente' => $cliente,
                'message' => 'Cliente guardado exitosamente'
            ]);
            exit();
            
        } catch (Exception $e) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit();
        }
    }
}

// Cargar datos
$clientes = [];
$productos = [];

try {
    // Clientes activos
    $stmt = $db->query("SELECT id, nombre, telefono, email, saldo_deuda, limite_credito 
                       FROM clientes WHERE activo = 1 ORDER BY nombre");
    $clientes = $stmt->fetchAll();
    
    // Productos con stock disponible
    $stmt = $db->query("SELECT sp.*, 
                              COALESCE(p.codigo, 'SIN-CAT') as categoria,
                              COALESCE(c.nombre, 'Sin categoría') as categoria_nombre
                       FROM subpaquetes sp 
                       LEFT JOIN paquetes p ON sp.paquete_id = p.id 
                       LEFT JOIN categorias c ON p.categoria_id = c.id
                       WHERE sp.activo = 1 AND sp.stock > 0 
                       ORDER BY categoria_nombre, sp.nombre_color");
    $productos = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Error cargando datos: " . $e->getMessage();
}

// Mostrar alertas de sesión
Funciones::mostrarAlertaSesion();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Punto de Venta - Sistema Lanas</title>
    <?php include 'header.php'; ?>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --verde-principal: #28a745;
            --verde-claro: #d4edda;
            --verde-oscuro: #1e7e34;
            --gris-claro: #f8f9fa;
            --gris-medio: #e9ecef;
            --gris-oscuro: #6c757d;
            --azul-claro: #e3f2fd;
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        
        /* Layout Principal */
        .main-wrapper {
            display: flex;
            min-height: 100vh;
        }
        

        /* Contenido principal */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            min-height: calc(100vh - 60px);
        }
        
        /* Punto de Venta */
        .pos-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .pos-header {
            background: linear-gradient(135deg, var(--verde-principal), var(--verde-oscuro));
            color: white;
            padding: 20px 30px;
            border-bottom: 3px solid rgba(255,255,255,0.2);
        }
        
        .pos-body {
            display: flex;
            min-height: 650px;
            max-height: calc(100vh - 200px); /* Limitar altura máxima */
            overflow: hidden;
        }
        
        /* Sección productos */
        .products-section {
            flex: 3;
            padding: 25px;
            background: var(--gris-claro);
            border-right: 2px solid var(--gris-medio);
            overflow-y: auto;
            height: calc(100vh - 250px); /* Altura ajustada dinámicamente */
            min-height: 500px;
        }
        
        .search-container {
            position: relative;
            margin-bottom: 25px;
        }
        
        .search-container i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--verde-principal);
            z-index: 2;
        }
        
        .search-container input {
            padding: 12px 20px 12px 50px;
            border: 2px solid var(--gris-medio);
            border-radius: 30px;
            font-size: 16px;
            width: 100%;
            transition: all 0.3s;
        }
        
        .search-container input:focus {
            border-color: var(--verde-principal);
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.25);
            outline: none;
        }
        
        /* Grid de productos */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .product-card {
            background: white;
            border: 2px solid var(--gris-medio);
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            border-color: var(--verde-principal);
            box-shadow: 0 15px 30px rgba(40, 167, 69, 0.15);
        }
        
        .product-card.selected {
            background: var(--verde-claro);
            border-color: var(--verde-principal);
            animation: pulse 0.5s ease-in-out;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.03); }
            100% { transform: scale(1); }
        }
        
        .product-color {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 15px;
            background: linear-gradient(135deg, var(--verde-principal), #20c997);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .product-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1rem;
            margin-bottom: 8px;
            height: 40px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        .product-code {
            color: var(--gris-oscuro);
            font-size: 0.85rem;
            margin-bottom: 10px;
        }
        
        .product-price {
            color: var(--verde-principal);
            font-weight: 700;
            font-size: 1.4rem;
            margin: 15px 0;
        }
        
        .product-stock {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 10px;
        }
        
        .stock-ok {
            background: var(--verde-claro);
            color: var(--verde-oscuro);
        }
        
        .stock-low {
            background: #fff3cd;
            color: #856404;
            font-weight: 600;
        }
        
        .stock-critico {
            background: #f8d7da;
            color: #721c24;
            font-weight: 700;
            animation: blink 1.5s infinite;
        }
        
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        /* Sección carrito - MEJORADA */
        .cart-section {
            flex: 2;
            padding: 25px;
            background: white;
            display: flex;
            flex-direction: column;
            min-width: 450px;
            height: calc(100vh - 250px); 
            overflow-y: auto;
        }
        
        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--gris-medio);
        }
        
        .cart-items-container {
            flex: 1;
            overflow-y: auto;
            min-height: 200px;
            max-height: 350px; /* Limitar altura máxima */
            margin-bottom: 20px;
            border: 2px solid var(--gris-medio);
            border-radius: 10px;
            padding: 15px;
            background: #fafafa;
        }
        
        .cart-item {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid var(--verde-principal);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.2s;
        }
        
        .cart-item:hover {
            transform: translateX(3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .cart-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .cart-item-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1rem;
            flex: 1;
        }
        
        .cart-item-price {
            color: var(--verde-principal);
            font-weight: 700;
            font-size: 1.1rem;
            white-space: nowrap;
        }
        
        .cart-item-body {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--gris-claro);
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid var(--gris-medio);
        }
        
        .qty-btn {
            width: 32px;
            height: 32px;
            border: none;
            background: var(--verde-principal);
            color: white;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            font-weight: 600;
        }
        
        .qty-btn:hover {
            background: var(--verde-oscuro);
            transform: scale(1.1);
        }
        
        .qty-input {
            width: 50px;
            text-align: center;
            border: 1px solid var(--gris-medio);
            border-radius: 6px;
            padding: 6px;
            font-weight: 700;
            font-size: 1rem;
            background: white;
        }
        
        .cart-item-total {
            font-weight: 700;
            color: var(--verde-oscuro);
            font-size: 1.2rem;
            min-width: 80px;
            text-align: right;
        }
        
        .remove-btn {
            color: #dc3545;
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            transition: all 0.2s;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .remove-btn:hover {
            background: #f8d7da;
            color: #bd2130;
            transform: scale(1.1);
        }
        
        /* Resumen */
        .cart-summary {
            background: linear-gradient(135deg, var(--gris-claro), white);
            padding: 25px;
            border-radius: 12px;
            border: 2px solid var(--gris-medio);
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-top: auto;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px dashed var(--gris-medio);
            font-size: 1rem;
        }
        
        .summary-total {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--verde-principal);
            margin-top: 20px;
            padding-top: 20px;
            border-top: 3px solid var(--verde-principal);
        }
        
        /* Métodos de pago */
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin: 20px 0;
        }
        
        .payment-option {
            text-align: center;
            padding: 18px 10px;
            border: 2px solid var(--gris-medio);
            border-radius: 10px;
            cursor: pointer;
            background: white;
            transition: all 0.2s;
        }
        
        .payment-option:hover {
            border-color: var(--verde-principal);
            transform: translateY(-2px);
        }
        
        .payment-option.active {
            border-color: var(--verde-principal);
            background: var(--verde-claro);
            color: var(--verde-oscuro);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.2);
        }
        
        .payment-option i {
            font-size: 2rem;
            margin-bottom: 8px;
            display: block;
            color: var(--verde-principal);
        }
        
        .payment-option.active i {
            color: var(--verde-oscuro);
        }
        
        /* Botones */
        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 25px;
        }
        
        .btn-process {
            background: linear-gradient(135deg, var(--verde-principal), var(--verde-oscuro));
            border: none;
            color: white;
            padding: 18px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            letter-spacing: 0.5px;
        }
        
        .btn-process:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(40, 167, 69, 0.3);
        }
        
        .btn-process:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .btn-clear {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none;
            color: white;
            padding: 18px;
            font-weight: 600;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-clear:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(220, 53, 69, 0.3);
        }
        
        /* Empty states */
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
            color: var(--gris-oscuro);
        }
        
        .empty-cart i {
            font-size: 5rem;
            color: var(--gris-medio);
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        /* Alertas toast */
        .alert-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 350px;
            animation: slideInRight 0.3s ease-out;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        @keyframes slideInRight {
            from { transform: translateX(100px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        /* Cliente info */
        .cliente-info {
            background: var(--azul-claro);
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
            border-left: 4px solid #2196f3;
            display: none;
        }
        
        .cliente-info.show {
            display: block;
            animation: fadeIn 0.3s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Scrollbar personalizado */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--gris-claro);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--verde-principal);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--verde-oscuro);
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
            
            .cart-section {
                min-width: 400px;
            }
        }
        
        @media (max-width: 992px) {
            .pos-body {
                flex-direction: column;
                max-height: none;
                min-height: auto;
            }
            
            .products-section {
                border-right: none;
                border-bottom: 2px solid var(--gris-medio);
                height: 500px; 
                min-height: 500px;
                flex: none;
            }
            
            .cart-section {
                min-width: auto;
                height: auto; 
                max-height: 600px;
            }
            
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            
            .menu-toggle {
                display: block;
            }
        }
        
        @media (max-width: 768px) {
            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
            }
            
            .payment-methods {
                grid-template-columns: 1fr;
            }
            
            .cart-item-body {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .cart-item-total {
                align-self: flex-end;
            }
        }
        
        /* Badge de categorías */
        .category-badge {
            display: inline-block;
            padding: 4px 10px;
            background: #e8f4fd;
            color: #2196f3;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-top: 8px;
        }
        
        /* Estado del carrito */
        .cart-status {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            color: var(--gris-oscuro);
        }
        
        .cart-total-items {
            font-weight: 600;
            color: var(--verde-principal);
        }
        
        /* Títulos */
        .section-title {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--gris-medio);
        }
    </style>
</head>
<body>
  
    <?php include 'sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        
        
        <!-- Contenido principal -->
        <div class="pos-container mt-4">
            <!-- Header POS -->
            <div class="pos-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-2"><i class="fas fa-shopping-cart me-2"></i>Carrito de Compras</h2>
                        <div class="cart-status">
                            <span><i class="fas fa-box me-1"></i> <span id="productCount"><?php echo count($productos); ?></span> productos disponibles</span>
                            <span class="ms-3"><i class="fas fa-shopping-basket me-1"></i> <span id="cartCount" class="cart-total-items">0</span> en carrito</span>
                        </div>
                    </div>
                    <div>
                        <button class="btn btn-outline-light me-2" onclick="location.reload()">
                            <i class="fas fa-sync"></i>
                        </button>
                        <button class="btn btn-outline-light" onclick="window.open('modulo_reportes.php', '_blank')">
                            <i class="fas fa-history"></i> Historial
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Cuerpo POS -->
            <div class="pos-body">
                <!-- SECCIÓN PRODUCTOS -->
                <div class="products-section">
                    <!-- Filtros -->
                    <div class="search-container">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" class="form-control" 
                               placeholder="Buscar productos por nombre, código o categoría...">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Filtrar por categoría:</label>
                        <div class="d-flex flex-wrap gap-2" id="categoryFilter">
                            <button class="btn btn-sm btn-outline-success active" data-category="all">
                                Todas
                            </button>
                            <?php
                            $categorias = [];
                            foreach ($productos as $producto) {
                                $cat = $producto['categoria_nombre'] ?? 'Sin categoría';
                                if (!isset($categorias[$cat])) {
                                    $categorias[$cat] = 0;
                                }
                                $categorias[$cat]++;
                            }
                            
                            foreach ($categorias as $categoria => $cantidad): ?>
                                <button class="btn btn-sm btn-outline-success" data-category="<?php echo htmlspecialchars($categoria); ?>">
                                    <?php echo htmlspecialchars($categoria); ?>
                                    <span class="badge bg-secondary ms-1"><?php echo $cantidad; ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Grid de productos -->
                    <div class="product-grid" id="productGrid">
                        <?php if (empty($productos)): ?>
                            <div class="col-12 text-center py-5">
                                <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
                                <h4 class="text-muted">No hay productos disponibles</h4>
                            </div>
                        <?php else: ?>
                            <?php foreach ($productos as $producto): 
                                $stock = $producto['stock'];
                                $min_stock = $producto['min_stock'] ?? 5;
                                $stock_class = 'stock-ok';
                                if ($stock <= 2) {
                                    $stock_class = 'stock-critico';
                                } elseif ($stock <= $min_stock) {
                                    $stock_class = 'stock-low';
                                }
                            ?>
                                <div class="product-card" 
                                     data-id="<?php echo $producto['id']; ?>"
                                     data-nombre="<?php echo htmlspecialchars($producto['nombre_color']); ?>"
                                     data-codigo="<?php echo htmlspecialchars($producto['codigo_color']); ?>"
                                     data-precio="<?php echo $producto['precio_venta']; ?>"
                                     data-stock="<?php echo $producto['stock']; ?>"
                                     data-categoria="<?php echo htmlspecialchars($producto['categoria_nombre'] ?? 'Sin categoría'); ?>">
                                    
                                    <div class="product-color">
                                        <i class="fas fa-shirt"></i>
                                    </div>
                                    
                                    <h6 class="product-name">
                                        <?php echo htmlspecialchars($producto['nombre_color']); ?>
                                    </h6>
                                    
                                    <div class="product-code">
                                        <i class="fas fa-barcode me-1"></i>
                                        <?php echo htmlspecialchars($producto['codigo_color']); ?>
                                    </div>
                                    
                                    <div class="product-price">
                                        <?php echo Funciones::formatearMoneda($producto['precio_venta']); ?>
                                    </div>
                                    
                                    <div class="product-stock <?php echo $stock_class; ?>">
                                        <i class="fas fa-box me-1"></i>
                                        Stock: <?php echo $producto['stock']; ?>
                                    </div>
                                    
                                    <div class="category-badge">
                                        <?php echo htmlspecialchars($producto['categoria_nombre'] ?? 'Sin categoría'); ?>
                                    </div>
                                    
                                    <button class="btn btn-success btn-sm w-100 mt-3" 
                                            onclick="agregarAlCarrito(<?php echo $producto['id']; ?>)">
                                        <i class="fas fa-cart-plus me-1"></i> Agregar
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- SECCIÓN CARRITO -->
                <div class="cart-section">
                    <!-- Cliente -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="fas fa-user me-1"></i> Cliente
                        </label>
                        <div class="input-group">
                            <select id="clienteSelect" class="form-select">
                                <option value="0">👤 Consumidor Final</option>
                                <?php foreach ($clientes as $cliente): ?>
                                    <option value="<?php echo $cliente['id']; ?>"
                                            data-deuda="<?php echo $cliente['saldo_deuda']; ?>"
                                            data-limite="<?php echo $cliente['limite_credito']; ?>"
                                            data-nombre="<?php echo htmlspecialchars($cliente['nombre']); ?>">
                                        <?php echo htmlspecialchars($cliente['nombre']); ?>
                                        <?php if ($cliente['saldo_deuda'] > 0): ?>
                                            <span class="text-danger ms-2">
                                                (Deuda: <?php echo Funciones::formatearMoneda($cliente['saldo_deuda']); ?>)
                                            </span>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-outline-success" type="button" onclick="nuevoCliente()">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        
                        <div id="clienteInfo" class="cliente-info"></div>
                    </div>
                    
                    <!-- Items del carrito -->
                    <div class="cart-header">
                        <h4 class="mb-0">
                            <i class="fas fa-shopping-cart me-2"></i>
                            Productos Seleccionados
                        </h4>
                        <span class="badge bg-success fs-6" id="cartCountBadge">0</span>
                    </div>
                    
                    <div class="cart-items-container" id="cartItems">
                        <div class="empty-cart" id="emptyCart">
                            <i class="fas fa-shopping-basket"></i>
                            <h4>Carrito vacío</h4>
                            <p class="text-muted">Agrega productos desde la lista</p>
                        </div>
                    </div>
                    
                    <!-- Resumen -->
                    <div class="cart-summary">
                        <h5 class="section-title mb-3">Resumen de Compra</h5>
                        
                        <div class="summary-row">
                            <span>Subtotal:</span>
                            <span id="subtotalDisplay">$0.00</span>
                        </div>
                        
                        <div class="summary-row">
                            <span>Descuento:</span>
                            <div class="d-flex align-items-center gap-2">
                                <input type="number" id="descuentoInput" class="form-control form-control-sm" 
                                       value="0" min="0" step="0.01" style="width: 120px;" 
                                       onchange="actualizarTotales()">
                                <small class="text-muted">$</small>
                            </div>
                        </div>
                        
                        <div class="summary-row">
                            <span>IVA (12%):</span>
                            <span id="ivaDisplay">$0.00</span>
                        </div>
                        
                        <div class="summary-row summary-total">
                            <span>TOTAL:</span>
                            <span id="totalDisplay" class="fw-bold">$0.00</span>
                        </div>
                        
                        <!-- Método de pago -->
                        <div class="mt-4">
                            <label class="form-label fw-bold mb-2">Método de Pago</label>
                            <div class="payment-methods">
                                <div class="payment-option active" data-payment="contado" onclick="seleccionarPago('contado')">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <div>Contado</div>
                                </div>
                                <div class="payment-option" data-payment="credito" onclick="seleccionarPago('credito')">
                                    <i class="fas fa-credit-card"></i>
                                    <div>Crédito</div>
                                </div>
                                <div class="payment-option" data-payment="mixto" onclick="seleccionarPago('mixto')">
                                    <i class="fas fa-percentage"></i>
                                    <div>Mixto</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Observaciones -->
                        <div class="mt-4">
                            <label class="form-label fw-bold mb-2">
                                <i class="fas fa-sticky-note me-1"></i> Observaciones
                            </label>
                            <textarea id="observacionesInput" class="form-control" rows="2" 
                                      placeholder="Notas adicionales para la venta..."></textarea>
                        </div>
                        
                        <!-- Botones de acción -->
                        <div class="action-buttons">
                            <button class="btn-process" onclick="procesarVenta()" id="btnProcesar">
                                <i class="fas fa-check-circle"></i>
                                PROCESAR VENTA
                            </button>
                            <button class="btn-clear" onclick="limpiarCarrito()">
                                <i class="fas fa-trash"></i>
                                LIMPIAR TODO
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal nuevo cliente -->
    <div class="modal fade" id="clienteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i> Nuevo Cliente
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formCliente" onsubmit="guardarCliente(event)">
                        <div class="mb-3">
                            <label class="form-label">Nombre Completo *</label>
                            <input type="text" class="form-control" name="nombre" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Teléfono</label>
                                <input type="tel" class="form-control" name="telefono">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Límite de Crédito</label>
                            <input type="number" class="form-control" name="limite_credito" value="500" step="0.01" min="0">
                        </div>
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-save me-2"></i> Guardar Cliente
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Variables globales
        let carrito = [];
        let metodoPago = 'contado';
        
        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            cargarCarritoGuardado();
            actualizarCarritoUI();
            //iniciarReloj();
            configurarFiltros();
            
            // Configurar búsqueda
            document.getElementById('searchInput').addEventListener('input', function() {
                buscarProductos(this.value);
            });
            
            // Configurar descuento
            document.getElementById('descuentoInput').addEventListener('input', actualizarTotales);
            
            // Configurar cliente
            document.getElementById('clienteSelect').addEventListener('change', actualizarInfoCliente);
            
            // Mostrar notificación si hay carrito guardado
            if (carrito.length > 0) {
                mostrarToast('Carrito recuperado de sesión anterior', 'info');
            }
        });
        
        // Iniciar reloj
        function iniciarReloj() {
            function actualizarHora() {
                const ahora = new Date();
                const hora = ahora.getHours().toString().padStart(2, '0');
                const minutos = ahora.getMinutes().toString().padStart(2, '0');
                const segundos = ahora.getSeconds().toString().padStart(2, '0');
                document.getElementById('currentTime').textContent = `${hora}:${minutos}:${segundos}`;
            }
            setInterval(actualizarHora, 1000);
        }
        
        // Configurar filtros por categoría
        function configurarFiltros() {
            const filtros = document.querySelectorAll('#categoryFilter button');
            filtros.forEach(filtro => {
                filtro.addEventListener('click', function() {
                    filtros.forEach(f => f.classList.remove('active'));
                    this.classList.add('active');
                    
                    const categoria = this.dataset.category;
                    const productos = document.querySelectorAll('.product-card');
                    
                    productos.forEach(producto => {
                        if (categoria === 'all' || producto.dataset.categoria === categoria) {
                            producto.style.display = 'block';
                        } else {
                            producto.style.display = 'none';
                        }
                    });
                });
            });
        }
        
        // Buscar productos
        function buscarProductos(termino) {
            const productos = document.querySelectorAll('.product-card');
            let encontrados = 0;
            
            productos.forEach(producto => {
                const nombre = producto.dataset.nombre.toLowerCase();
                const codigo = producto.dataset.codigo.toLowerCase();
                const buscar = termino.toLowerCase();
                
                if (buscar === '' || nombre.includes(buscar) || codigo.includes(buscar)) {
                    producto.style.display = 'block';
                    encontrados++;
                } else {
                    producto.style.display = 'none';
                }
            });
        }
        
        // AGREGAR AL CARRITO
        function agregarAlCarrito(productoId) {
            const productoElement = document.querySelector(`.product-card[data-id="${productoId}"]`);
            if (!productoElement) {
                mostrarToast('Producto no encontrado', 'danger');
                return;
            }
            
            const producto = {
                id: parseInt(productoId),
                nombre: productoElement.dataset.nombre,
                codigo: productoElement.dataset.codigo,
                precio: parseFloat(productoElement.dataset.precio),
                stock: parseInt(productoElement.dataset.stock)
            };
            
            const index = carrito.findIndex(item => item.id === producto.id);
            
            if (index !== -1) {
                if (carrito[index].cantidad < producto.stock) {
                    carrito[index].cantidad++;
                    mostrarToast(`+1 ${producto.nombre}`, 'success');
                } else {
                    mostrarToast(`Stock insuficiente para ${producto.nombre}`, 'warning');
                    return;
                }
            } else {
                producto.cantidad = 1;
                carrito.push(producto);
                mostrarToast(`✓ Agregado: ${producto.nombre}`, 'success');
            }
            
            actualizarCarritoUI();
            guardarCarritoStorage();
            
            productoElement.classList.add('selected');
            setTimeout(() => productoElement.classList.remove('selected'), 500);
        }
        
        // Actualizar interfaz del carrito
        function actualizarCarritoUI() {
            const cartItems = document.getElementById('cartItems');
            const emptyCart = document.getElementById('emptyCart');
            const cartCount = document.getElementById('cartCount');
            const cartCountBadge = document.getElementById('cartCountBadge');
            
            if (carrito.length === 0) {
                if (emptyCart) emptyCart.style.display = 'block';
                cartItems.innerHTML = '';
                if (cartCount) cartCount.textContent = '0';
                if (cartCountBadge) cartCountBadge.textContent = '0';
            } else {
                if (emptyCart) emptyCart.style.display = 'none';
                
                let html = '';
                let totalItems = 0;
                
                carrito.forEach((item, index) => {
                    const subtotal = item.precio * item.cantidad;
                    totalItems += item.cantidad;
                    
                    html += `
                    <div class="cart-item">
                        <div class="cart-item-header">
                            <div class="cart-item-name">${item.nombre}</div>
                            <div class="cart-item-price">${formatearMoneda(item.precio)}</div>
                        </div>
                        <div class="cart-item-body">
                            <div class="quantity-controls">
                                <button class="qty-btn" onclick="modificarCantidad(${index}, -1)" title="Disminuir">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input type="number" class="qty-input" 
                                       value="${item.cantidad}" min="1" max="${item.stock}"
                                       onchange="cambiarCantidad(${index}, this.value)">
                                <button class="qty-btn" onclick="modificarCantidad(${index}, 1)" title="Aumentar">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <div class="cart-item-total">
                                ${formatearMoneda(subtotal)}
                            </div>
                            <button class="remove-btn" onclick="eliminarDelCarrito(${index})" title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    `;
                });
                
                cartItems.innerHTML = html;
                if (cartCount) cartCount.textContent = totalItems;
                if (cartCountBadge) cartCountBadge.textContent = totalItems;
            }
            
            actualizarTotales();
        }
        
        // Modificar cantidad
        function modificarCantidad(index, cambio) {
            const nuevaCantidad = carrito[index].cantidad + cambio;
            
            if (nuevaCantidad < 1) {
                eliminarDelCarrito(index);
                return;
            }
            
            if (nuevaCantidad > carrito[index].stock) {
                mostrarToast('No hay suficiente stock disponible', 'warning');
                return;
            }
            
            carrito[index].cantidad = nuevaCantidad;
            actualizarCarritoUI();
            guardarCarritoStorage();
        }
        
        // Cambiar cantidad manualmente
        function cambiarCantidad(index, valor) {
            const cantidad = parseInt(valor);
            
            if (isNaN(cantidad) || cantidad < 1) {
                eliminarDelCarrito(index);
                return;
            }
            
            if (cantidad > carrito[index].stock) {
                mostrarToast('No hay suficiente stock disponible', 'warning');
                carrito[index].cantidad = carrito[index].stock;
            } else {
                carrito[index].cantidad = cantidad;
            }
            
            actualizarCarritoUI();
            guardarCarritoStorage();
        }
        
        // Eliminar del carrito
        function eliminarDelCarrito(index) {
            const producto = carrito[index];
            carrito.splice(index, 1);
            
            mostrarToast(`✗ Eliminado: ${producto.nombre}`, 'info');
            actualizarCarritoUI();
            guardarCarritoStorage();
        }
        
        // Actualizar totales
        function actualizarTotales() {
            let subtotal = 0;
            carrito.forEach(item => {
                subtotal += item.precio * item.cantidad;
            });
            
            const descuento = parseFloat(document.getElementById('descuentoInput').value) || 0;
            const totalSinIVA = Math.max(0, subtotal - descuento);
            const iva = totalSinIVA * 0.12;
            const total = totalSinIVA + iva;
            
            document.getElementById('subtotalDisplay').textContent = formatearMoneda(subtotal);
            document.getElementById('ivaDisplay').textContent = formatearMoneda(iva);
            document.getElementById('totalDisplay').textContent = formatearMoneda(total);
        }
        
        // Actualizar info del cliente
        function actualizarInfoCliente() {
            const select = document.getElementById('clienteSelect');
            const option = select.options[select.selectedIndex];
            const clienteInfo = document.getElementById('clienteInfo');
            
            if (option.value > 0) {
                const deuda = parseFloat(option.dataset.deuda) || 0;
                const limite = parseFloat(option.dataset.limite) || 0;
                const nombre = option.dataset.nombre || '';
                
                let infoHTML = `
                    <div class="d-flex justify-content-between">
                        <span>Cliente:</span>
                        <strong>${nombre}</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Deuda actual:</span>
                        <span class="${deuda > 0 ? 'text-danger fw-bold' : 'text-success'}">
                            ${formatearMoneda(deuda)}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Límite crédito:</span>
                        <span class="text-primary">${formatearMoneda(limite)}</span>
                    </div>
                `;
                
                clienteInfo.innerHTML = infoHTML;
                clienteInfo.classList.add('show');
            } else {
                clienteInfo.classList.remove('show');
            }
        }
        
        // Seleccionar método de pago
        function seleccionarPago(tipo) {
            metodoPago = tipo;
            
            document.querySelectorAll('.payment-option').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`.payment-option[data-payment="${tipo}"]`).classList.add('active');
        }
        
        // Procesar venta
        async function procesarVenta() {
            if (carrito.length === 0) {
                mostrarToast('El carrito está vacío', 'warning');
                return;
            }
            
            const clienteId = document.getElementById('clienteSelect').value;
            const descuento = document.getElementById('descuentoInput').value;
            const observaciones = document.getElementById('observacionesInput').value;
            
            // Validaciones
            if (metodoPago === 'credito' && clienteId == 0) {
                mostrarToast('Debe seleccionar un cliente para ventas a crédito', 'warning');
                return;
            }
            
            // Verificar stock
            for (let item of carrito) {
                if (item.cantidad > item.stock) {
                    mostrarToast(`Stock insuficiente para ${item.nombre}`, 'danger');
                    return;
                }
            }
            
            // Confirmar
            const total = document.getElementById('totalDisplay').textContent;
            const clienteNombre = document.getElementById('clienteSelect').selectedOptions[0].text;
            
            if (!confirm(`¿Confirmar venta por ${total}?\n\nCliente: ${clienteNombre}\nTipo de pago: ${metodoPago.toUpperCase()}\n\n¿Desea continuar?`)) {
                return;
            }
            
            // Preparar datos
            const formData = new FormData();
            formData.append('accion', 'procesar_venta');
            formData.append('cliente_id', clienteId);
            formData.append('tipo_pago', metodoPago);
            formData.append('descuento', descuento);
            formData.append('observaciones', observaciones);
            formData.append('productos', JSON.stringify(carrito.map(p => ({
                id: p.id,
                cantidad: p.cantidad
            }))));
            
            // Deshabilitar botón
            const btn = document.getElementById('btnProcesar');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> PROCESANDO...';
            btn.disabled = true;
            
            try {
                const response = await fetch('modulo_ventas.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Limpiar carrito
                    carrito = [];
                    localStorage.removeItem('carrito_venta');
                    actualizarCarritoUI();
                    
                    mostrarToast('¡Venta procesada exitosamente!', 'success');
                    
                    // Redirigir después de 1 segundo
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1000);
                } else {
                    throw new Error(data.error || 'Error desconocido');
                }
                
            } catch (error) {
                console.error('Error:', error);
                mostrarToast('Error al procesar la venta: ' + error.message, 'danger');
                
                // Restaurar botón
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            }
        }
        
        // Nuevo cliente
        function nuevoCliente() {
            const modal = new bootstrap.Modal(document.getElementById('clienteModal'));
            modal.show();
        }
        
        // Guardar cliente
        async function guardarCliente(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            formData.append('accion', 'guardar_cliente');
            
            try {
                const response = await fetch('modulo_ventas.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Agregar cliente al select
                    const select = document.getElementById('clienteSelect');
                    const option = document.createElement('option');
                    option.value = data.cliente.id;
                    option.textContent = data.cliente.nombre;
                    option.setAttribute('data-deuda', data.cliente.saldo_deuda);
                    option.setAttribute('data-limite', data.cliente.limite_credito);
                    option.setAttribute('data-nombre', data.cliente.nombre);
                    select.appendChild(option);
                    select.value = data.cliente.id;
                    
                    // Actualizar info del cliente
                    actualizarInfoCliente();
                    
                    // Cerrar modal
                    bootstrap.Modal.getInstance(document.getElementById('clienteModal')).hide();
                    
                    // Limpiar formulario
                    event.target.reset();
                    
                    mostrarToast(data.message, 'success');
                } else {
                    throw new Error(data.error || 'Error desconocido');
                }
                
            } catch (error) {
                console.error('Error:', error);
                mostrarToast('Error al guardar cliente: ' + error.message, 'danger');
            }
        }
        
        // Funciones auxiliares
        function formatearMoneda(amount) {
            return '$' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }
        
        function mostrarToast(mensaje, tipo = 'info') {
            const iconos = {
                'success': 'check-circle',
                'danger': 'exclamation-triangle',
                'warning': 'exclamation-triangle',
                'info': 'info-circle'
            };
            
            const toast = document.createElement('div');
            toast.className = `alert alert-${tipo} alert-dismissible fade show alert-toast`;
            toast.innerHTML = `
                <i class="fas fa-${iconos[tipo] || 'info-circle'} me-2"></i>
                ${mensaje}
                <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 3000);
        }
        
        function guardarCarritoStorage() {
            localStorage.setItem('carrito_venta', JSON.stringify(carrito));
        }
        
        function cargarCarritoGuardado() {
            try {
                const guardado = localStorage.getItem('carrito_venta');
                if (guardado) {
                    carrito = JSON.parse(guardado);
                }
            } catch(e) {
                console.error('Error cargando carrito:', e);
                localStorage.removeItem('carrito_venta');
            }
        }
        
        function limpiarCarrito() {
            if (carrito.length === 0) {
                mostrarToast('El carrito ya está vacío', 'info');
                return;
            }
            
            if (confirm('¿Está seguro de limpiar el carrito?')) {
                carrito = [];
                localStorage.removeItem('carrito_venta');
                actualizarCarritoUI();
                mostrarToast('Carrito limpiado', 'success');
            }
        }
    </script>
</body>
</html>