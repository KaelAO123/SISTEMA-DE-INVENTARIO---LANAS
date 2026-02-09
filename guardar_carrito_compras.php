<?php
require_once 'database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Obtener datos JSON del cuerpo de la petición
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['carrito'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos inválidos']);
    exit;
}

$carrito = $input['carrito'];
$session_id = session_id();
$usuario_id = $_SESSION['usuario_id'] ?? null; // Si tienes usuarios autenticados

try {
    $db = getDB();
    
    // Opción 1: Guardar como venta definitiva
    if (isset($input['finalizar_compra']) && $input['finalizar_compra']) {
        $db->beginTransaction();
        
        foreach ($carrito as $item) {
            // 1. Insertar en ventas
            $stmt = $db->prepare("
                INSERT INTO ventas 
                (producto_id, nombre_producto, codigo_producto, precio, cantidad, total)
                VALUES (:producto_id, :nombre, :codigo, :precio, :cantidad, :total)
            ");
            
            $total = $item['precio'] * $item['cantidad'];
            
            $stmt->execute([
                ':producto_id' => $item['id'],
                ':nombre' => $item['nombre'],
                ':codigo' => $item['codigo'],
                ':precio' => $item['precio'],
                ':cantidad' => $item['cantidad'],
                ':total' => $total
            ]);
            
            // 2. Actualizar stock (si tienes tabla de productos)
            $stmt = $db->prepare("
                UPDATE productos 
                SET stock = stock - :cantidad 
                WHERE id = :producto_id
            ");
            $stmt->execute([
                ':cantidad' => $item['cantidad'],
                ':producto_id' => $item['id']
            ]);
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Venta registrada exitosamente',
            'total_items' => count($carrito)
        ]);
        
    } else {
        // Opción 2: Guardar carrito temporalmente
        // Primero limpiar carrito anterior de esta sesión
        $stmt = $db->prepare("DELETE FROM carritos_temporales WHERE session_id = :session_id");
        $stmt->execute([':session_id' => $session_id]);
        
        // Insertar cada item
        foreach ($carrito as $item) {
            $stmt = $db->prepare("
                INSERT INTO carritos_temporales 
                (session_id, usuario_id, producto_id, cantidad)
                VALUES (:session_id, :usuario_id, :producto_id, :cantidad)
            ");
            
            $stmt->execute([
                ':session_id' => $session_id,
                ':usuario_id' => $usuario_id,
                ':producto_id' => $item['id'],
                ':cantidad' => $item['cantidad']
            ]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Carrito guardado temporalmente',
            'items' => count($carrito)
        ]);
    }
    
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log("Error al guardar carrito: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Error al guardar el carrito',
        'debug' => $e->getMessage() // Solo en desarrollo
    ]);
}
?>