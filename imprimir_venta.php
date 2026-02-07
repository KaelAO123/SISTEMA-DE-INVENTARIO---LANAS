<?php
// imprimir_venta.php - Generar y imprimir recibo

require_once 'database.php';
require_once 'funciones.php';

Funciones::verificarSesion();

$db = getDB();
$venta_id = $_GET['id'] ?? ($_SESSION['venta_id'] ?? 0);

if (!$venta_id) {
    die("No se especificó una venta");
}

// Obtener datos de la venta
try {
    $stmt = $db->prepare("SELECT v.*, c.nombre as cliente_nombre, u.nombre as vendedor_nombre
                         FROM ventas v
                         LEFT JOIN clientes c ON v.cliente_id = c.id
                         JOIN usuarios u ON v.vendedor_id = u.id
                         WHERE v.id = ?");
    $stmt->execute([$venta_id]);
    $venta = $stmt->fetch();
    
    if (!$venta) {
        die("Venta no encontrada");
    }
    
    // Obtener detalles
    $stmt = $db->prepare("SELECT vd.*, sp.nombre_color, sp.codigo_color
                         FROM venta_detalles vd
                         JOIN subpaquetes sp ON vd.subpaquete_id = sp.id
                         WHERE vd.venta_id = ?");
    $stmt->execute([$venta_id]);
    $detalles = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Error al cargar venta: " . $e->getMessage());
}

// Limpiar carrito después de imprimir
unset($_SESSION['venta_id']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Venta <?php echo $venta['codigo_venta']; ?></title>
    <style>
        @media print {
            @page { margin: 0; size: 80mm auto; }
            body { 
                width: 80mm;
                margin: 0;
                padding: 10px;
                font-family: 'Courier New', monospace;
                font-size: 12px;
            }
            .no-print { display: none; }
        }
        
        .recibo {
            width: 80mm;
            margin: 0 auto;
            background: white;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px dashed #000;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        
        .header h1 {
            margin: 0;
            font-size: 16px;
            font-weight: bold;
        }
        
        .empresa {
            font-weight: bold;
            font-size: 14px;
        }
        
        .info {
            margin-bottom: 15px;
            font-size: 11px;
        }
        
        .info-item {
            margin-bottom: 3px;
        }
        
        .items {
            margin: 15px 0;
        }
        
        .item-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            padding-bottom: 3px;
            border-bottom: 1px dotted #ccc;
        }
        
        .item-name {
            flex: 2;
        }
        
        .item-qty {
            text-align: center;
            flex: 1;
        }
        
        .item-price {
            text-align: right;
            flex: 1;
        }
        
        .totales {
            margin-top: 20px;
            border-top: 2px solid #000;
            padding-top: 10px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .total-grande {
            font-size: 14px;
            font-weight: bold;
        }
        
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 10px;
            color: #666;
            border-top: 1px dashed #ccc;
            padding-top: 10px;
        }
        
        .action-buttons {
            text-align: center;
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .btn {
            margin: 5px;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .btn-print {
            background: #28a745;
            color: white;
        }
        
        .btn-pdf {
            background: #dc3545;
            color: white;
        }
        
        .btn-close {
            background: #6c757d;
            color: white;
        }
    </style>
</head>
<body>
    <div class="recibo">
        <div class="header">
            <div class="empresa">LANAS Y TEXTILES S.A.</div>
            <div>RECIBO DE VENTA</div>
            <div><?php echo date('d/m/Y H:i', strtotime($venta['fecha_hora'])); ?></div>
        </div>
        
        <div class="info">
            <div class="info-item">
                <strong>Venta:</strong> <?php echo $venta['codigo_venta']; ?>
            </div>
            <div class="info-item">
                <strong>Cliente:</strong> <?php echo $venta['cliente_nombre'] ?: 'Consumidor Final'; ?>
            </div>
            <div class="info-item">
                <strong>Vendedor:</strong> <?php echo $venta['vendedor_nombre']; ?>
            </div>
            <div class="info-item">
                <strong>Pago:</strong> <?php echo strtoupper($venta['tipo_pago']); ?>
            </div>
            <?php if ($venta['observaciones']): ?>
            <div class="info-item">
                <strong>Obs:</strong> <?php echo $venta['observaciones']; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="items">
            <div class="item-row" style="font-weight: bold; border-bottom: 2px solid #000;">
                <div class="item-name">PRODUCTO</div>
                <div class="item-qty">CANT</div>
                <div class="item-price">TOTAL</div>
            </div>
            
            <?php foreach ($detalles as $detalle): ?>
            <div class="item-row">
                <div class="item-name"><?php echo htmlspecialchars($detalle['nombre_color']); ?></div>
                <div class="item-qty"><?php echo $detalle['cantidad']; ?></div>
                <div class="item-price"><?php echo Funciones::formatearMoneda($detalle['subtotal']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="totales">
            <div class="total-row">
                <div>Subtotal:</div>
                <div><?php echo Funciones::formatearMoneda($venta['subtotal']); ?></div>
            </div>
            <div class="total-row">
                <div>Descuento:</div>
                <div>-<?php echo Funciones::formatearMoneda($venta['descuento']); ?></div>
            </div>
            <div class="total-row">
                <div>IVA (12%):</div>
                <div><?php echo Funciones::formatearMoneda($venta['iva']); ?></div>
            </div>
            <div class="total-row total-grande">
                <div>TOTAL:</div>
                <div><?php echo Funciones::formatearMoneda($venta['total']); ?></div>
            </div>
            
            <?php if ($venta['tipo_pago'] === 'credito'): ?>
            <div class="total-row" style="color: #dc3545;">
                <div>Saldo Pendiente:</div>
                <div><?php echo Funciones::formatearMoneda($venta['debe']); ?></div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            <div>*** GRACIAS POR SU COMPRA ***</div>
            <div>Sistema de Inventario Lanas</div>
            <div>www.lanasytextiles.com</div>
        </div>
    </div>
    
    <div class="action-buttons no-print">
        <button class="btn btn-print" onclick="window.print()">
            <i class="fas fa-print"></i> Imprimir Recibo
        </button>
        <button class="btn btn-pdf" onclick="generarPDF()">
            <i class="fas fa-file-pdf"></i> Descargar PDF
        </button>
        <button class="btn btn-close" onclick="window.close()">
            <i class="fas fa-times"></i> Cerrar
        </button>
    </div>
    
    <script>
        // Función para generar PDF
        function generarPDF() {
            // Aquí puedes integrar una librería como jsPDF
            alert('Función de PDF en desarrollo. Por ahora use la opción de imprimir.');
        }
        
        // Imprimir automáticamente
        window.onload = function() {
            setTimeout(() => {
                window.print();
            }, 1000);
        };
    </script>
</body>
</html>