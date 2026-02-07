<?php

require_once 'database.php';
require_once 'funciones.php';

Funciones::verificarSesion();

$db = getDB();
$action = $_GET['action'] ?? '';

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'obtener_paquete':
            $id = $_GET['id'] ?? 0;
            $stmt = $db->prepare("SELECT * FROM paquetes WHERE id = ?");
            $stmt->execute([$id]);
            $paquete = $stmt->fetch();
            
            if ($paquete) {
                echo json_encode(['success' => true, 'data' => $paquete]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Paquete no encontrado']);
            }
            break;
            
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
            $paquete = $stmt->fetch();
            
            if (!$paquete) {
                echo json_encode(['success' => false, 'error' => 'Paquete no encontrado']);
                break;
            }
            
            // Obtener subpaquetes del paquete
            $stmt = $db->prepare("SELECT * FROM subpaquetes WHERE paquete_id = ? AND activo = 1 ORDER BY nombre_color");
            $stmt->execute([$id]);
            $subpaquetes = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'paquete' => $paquete,
                'subpaquetes' => $subpaquetes
            ]);
            break;
            
        case 'obtener_subpaquete':
            $id = $_GET['id'] ?? 0;
            $stmt = $db->prepare("SELECT * FROM subpaquetes WHERE id = ?");
            $stmt->execute([$id]);
            $subpaquete = $stmt->fetch();
            
            if ($subpaquete) {
                echo json_encode(['success' => true, 'data' => $subpaquete]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Subpaquete no encontrado']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>