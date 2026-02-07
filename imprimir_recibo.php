<?php
require_once 'database.php';
require_once 'funciones.php';

Funciones::verificarSesion();

$db = getDB();
$venta = null;
$detalles = [];
$cliente = null;
$vendedor = null;
$error = '';

// Obtener ID de venta
$venta_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($venta_id > 0) {
    try {
        // Obtener datos de la venta
        $stmt = $db->prepare("SELECT v.*, 
                                    c.nombre as cliente_nombre,
                                    c.telefono as cliente_telefono,
                                    u.nombre as vendedor_nombre
                             FROM ventas v
                             LEFT JOIN clientes c ON v.cliente_id = c.id
                             LEFT JOIN usuarios u ON v.vendedor_id = u.id
                             WHERE v.id = ?");
        $stmt->execute([$venta_id]);
        $venta = $stmt->fetch();
        
        if (!$venta) {
            throw new Exception("Venta no encontrada");
        }
        
        // Obtener detalles de la venta
        $stmt = $db->prepare("SELECT vd.*, 
                                    sp.nombre_color,
                                    sp.codigo_color
                             FROM venta_detalles vd
                             JOIN subpaquetes sp ON vd.subpaquete_id = sp.id
                             WHERE vd.venta_id = ?
                             ORDER BY vd.id");
        $stmt->execute([$venta_id]);
        $detalles = $stmt->fetchAll();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
} else {
    $error = "ID de venta no válido";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Venta #<?php echo $venta['codigo_venta'] ?? ''; ?></title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        @media print {
            @page {
                margin: 0.5cm;
                size: letter;
            }
            
            body {
                margin: 0;
                padding: 0;
            }
            
            .no-print {
                display: none !important;
            }
            
            .recibo-container {
                box-shadow: none !important;
                border: none !important;
                page-break-after: avoid;
            }
        }
        
        @media screen {
            body {
                background: linear-gradient(135deg, #f5f5f5 0%, #e8f5e9 100%);
                padding: 20px;
            }
        }
        
        .recibo-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border-radius: 10px;
        }
        
        .recibo-header {
            text-align: center;
            border-bottom: 3px solid #28a745;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .recibo-header h1 {
            color: #28a745;
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: bold;
        }
        
        .recibo-header .subtitle {
            color: #666;
            font-size: 1.2rem;
        }
        
        .info-section {
            margin-bottom: 30px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .info-label {
            font-weight: bold;
            color: #333;
        }
        
        .info-value {
            color: #666;
        }
        
        .productos-table {
            width: 100%;
            margin-bottom: 30px;
            border-collapse: collapse;
        }
        
        .productos-table thead {
            background: #28a745;
            color: white;
        }
        
        .productos-table th,
        .productos-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        .productos-table th {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        
        .productos-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .productos-table .text-end {
            text-align: right;
        }
        
        .productos-table .text-center {
            text-align: center;
        }
        
        .totales-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #dee2e6;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 1.1rem;
        }
        
        .total-row.final {
            font-size: 1.5rem;
            font-weight: bold;
            color: #28a745;
            border-top: 3px solid #28a745;
            padding-top: 15px;
            margin-top: 10px;
        }
        
        .footer-section {
            margin-top: 50px;
            padding-top: 30px;
            border-top: 2px dashed #dee2e6;
            text-align: center;
            color: #666;
        }
        
        .firma {
            margin-top: 50px;
            padding-top: 20px;
        }
        
        .firma-linea {
            border-top: 2px solid #333;
            width: 300px;
            margin: 0 auto;
            padding-top: 10px;
        }
        
        .btn-actions {
            position: fixed;
            bottom: 30px;
            right: 30px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }
        
        .btn-action {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-action:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.4);
        }
        
        .btn-print {
            background: linear-gradient(135deg, #007bff, #0056b3);
        }
        
        .btn-pdf {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }
        
        .btn-back {
            background: linear-gradient(135deg, #6c757d, #5a6268);
        }
        
        .badge-estado {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
        }
        
        .badge-pagada {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-pendiente {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-anulada {
            background: #f8d7da;
            color: #721c24;
        }
        
        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <?php if ($error): ?>
        <div class="recibo-container">
            <div class="alert-error text-center">
                <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                <h3>Error</h3>
                <p><?php echo htmlspecialchars($error); ?></p>
                <button class="btn btn-primary mt-3" onclick="window.location.href='modulo_ventas.php'">
                    <i class="fas fa-arrow-left me-2"></i> Volver al Punto de Venta
                </button>
            </div>
        </div>
    <?php else: ?>
        <div class="recibo-container" id="reciboContent">
            <!-- Header -->
            <div class="recibo-header">
                <h1><i class="fas fa-receipt me-2"></i>RECIBO DE VENTA</h1>
                <div class="subtitle">LANAS Y TEXTILES</div>
                <div class="mt-3">
                    <strong>Código:</strong> <?php echo htmlspecialchars($venta['codigo_venta']); ?>
                </div>
            </div>
            
            <!-- Información General -->
            <div class="info-section">
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-row">
                            <span class="info-label">
                                <i class="fas fa-calendar me-2"></i>Fecha:
                            </span>
                            <span class="info-value">
                                <?php echo date('d/m/Y H:i', strtotime($venta['fecha_hora'])); ?>
                            </span>
                        </div>
                        
                        <div class="info-row">
                            <span class="info-label">
                                <i class="fas fa-user me-2"></i>Cliente:
                            </span>
                            <span class="info-value">
                                <?php echo htmlspecialchars($venta['cliente_nombre'] ?? 'Consumidor Final'); ?>
                            </span>
                        </div>
                        
                        <?php if ($venta['cliente_telefono']): ?>
                        <div class="info-row">
                            <span class="info-label">
                                <i class="fas fa-phone me-2"></i>Teléfono:
                            </span>
                            <span class="info-value">
                                <?php echo htmlspecialchars($venta['cliente_telefono']); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="info-row">
                            <span class="info-label">
                                <i class="fas fa-user-tie me-2"></i>Vendedor:
                            </span>
                            <span class="info-value">
                                <?php echo htmlspecialchars($venta['vendedor_nombre'] ?? 'N/A'); ?>
                            </span>
                        </div>
                        
                        <div class="info-row">
                            <span class="info-label">
                                <i class="fas fa-credit-card me-2"></i>Tipo de Pago:
                            </span>
                            <span class="info-value text-uppercase">
                                <?php echo htmlspecialchars($venta['tipo_pago']); ?>
                            </span>
                        </div>
                        
                        <div class="info-row">
                            <span class="info-label">
                                <i class="fas fa-info-circle me-2"></i>Estado:
                            </span>
                            <span class="info-value">
                                <span class="badge-estado badge-<?php echo $venta['estado']; ?>">
                                    <?php echo strtoupper($venta['estado']); ?>
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tabla de Productos -->
            <h4 class="mb-3"><i class="fas fa-shopping-bag me-2"></i>Productos</h4>
            <table class="productos-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Producto</th>
                        <th>Código</th>
                        <th class="text-center">Cantidad</th>
                        <th class="text-end">Precio Unit.</th>
                        <th class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $num = 1; ?>
                    <?php foreach ($detalles as $detalle): ?>
                        <tr>
                            <td><?php echo $num++; ?></td>
                            <td><?php echo htmlspecialchars($detalle['nombre_color']); ?></td>
                            <td><?php echo htmlspecialchars($detalle['codigo_color']); ?></td>
                            <td class="text-center"><?php echo $detalle['cantidad']; ?></td>
                            <td class="text-end"><?php echo Funciones::formatearMoneda($detalle['precio_unitario']); ?></td>
                            <td class="text-end"><?php echo Funciones::formatearMoneda($detalle['subtotal']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Totales -->
            <div class="totales-section">
                <div class="row">
                    <div class="col-md-6">
                        <?php if ($venta['observaciones']): ?>
                            <div class="alert alert-info">
                                <strong><i class="fas fa-sticky-note me-2"></i>Observaciones:</strong><br>
                                <?php echo nl2br(htmlspecialchars($venta['observaciones'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6">
                        <?php if ($venta['descuento'] > 0): ?>
                        <div class="total-row">
                            <span>Subtotal:</span>
                            <span><?php echo Funciones::formatearMoneda($venta['subtotal'] + $venta['descuento']); ?></span>
                        </div>
                        
                        <div class="total-row text-danger">
                            <span>Descuento:</span>
                            <span>-<?php echo Funciones::formatearMoneda($venta['descuento']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="total-row">
                            <span>Subtotal Neto:</span>
                            <span><?php echo Funciones::formatearMoneda($venta['subtotal']); ?></span>
                        </div>
                        
                        <div class="total-row">
                            <span>IVA (12%):</span>
                            <span><?php echo Funciones::formatearMoneda($venta['iva']); ?></span>
                        </div>
                        
                        <div class="total-row final">
                            <span>TOTAL:</span>
                            <span><?php echo Funciones::formatearMoneda($venta['total']); ?></span>
                        </div>
                        
                        <?php if ($venta['debe'] > 0): ?>
                        <div class="total-row text-danger">
                            <span>Pagado:</span>
                            <span><?php echo Funciones::formatearMoneda($venta['pagado']); ?></span>
                        </div>
                        
                        <div class="total-row text-danger">
                            <span><strong>Saldo Pendiente:</strong></span>
                            <span><strong><?php echo Funciones::formatearMoneda($venta['debe']); ?></strong></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Firma -->
            <div class="firma">
                <div class="firma-linea">
                    Firma del Cliente
                </div>
            </div>
            
            <!-- Footer -->
            <div class="footer-section">
                <p><strong>¡Gracias por su compra!</strong></p>
                <p class="small text-muted">
                    Este documento es un comprobante de venta válido<br>
                    Conserve este recibo para cualquier reclamo
                </p>
                <p class="small text-muted">
                    Impreso el: <?php echo date('d/m/Y H:i:s'); ?>
                </p>
            </div>
        </div>
        
        <!-- Botones de acción (no se imprimen) -->
        <div class="btn-actions no-print">
            <button class="btn-action btn-print" onclick="window.print()" title="Imprimir">
                <i class="fas fa-print"></i>
            </button>
            
            <button class="btn-action btn-pdf" onclick="descargarPDF()" title="Descargar PDF">
                <i class="fas fa-file-pdf"></i>
            </button>
            
            <button class="btn-action btn-back" onclick="window.location.href='modulo_ventas.php'" title="Nueva Venta">
                <i class="fas fa-arrow-left"></i>
            </button>
        </div>
    <?php endif; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jsPDF para generar PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    
    <script>
        // Descargar como PDF
        async function descargarPDF() {
            // Ocultar botones temporalmente
            const buttons = document.querySelector('.btn-actions');
            buttons.style.display = 'none';
            
            try {
                // Capturar el contenido como imagen
                const canvas = await html2canvas(document.getElementById('reciboContent'), {
                    scale: 2,
                    logging: false,
                    backgroundColor: '#ffffff'
                });
                
                // Crear PDF
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF('p', 'mm', 'letter');
                
                const imgWidth = 210; // Ancho de página carta en mm
                const imgHeight = (canvas.height * imgWidth) / canvas.width;
                
                const imgData = canvas.toDataURL('image/png');
                pdf.addImage(imgData, 'PNG', 0, 0, imgWidth, imgHeight);
                
                // Descargar
                const codigo = '<?php echo $venta['codigo_venta'] ?? 'recibo'; ?>';
                pdf.save(`Recibo_${codigo}_${Date.now()}.pdf`);
                
                // Mostrar mensaje de éxito
                mostrarNotificacion('PDF descargado exitosamente', 'success');
                
            } catch (error) {
                console.error('Error generando PDF:', error);
                mostrarNotificacion('Error al generar PDF', 'error');
            } finally {
                // Restaurar botones
                buttons.style.display = 'flex';
            }
        }
        
        // Mostrar notificación
        function mostrarNotificacion(mensaje, tipo) {
            const colores = {
                'success': '#28a745',
                'error': '#dc3545',
                'info': '#17a2b8'
            };
            
            const notif = document.createElement('div');
            notif.style.cssText = `
                position: fixed;
                top: 20px;
                left: 50%;
                transform: translateX(-50%);
                background: ${colores[tipo] || colores.info};
                color: white;
                padding: 15px 30px;
                border-radius: 10px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.3);
                z-index: 9999;
                animation: slideDown 0.3s ease-out;
            `;
            notif.textContent = mensaje;
            
            document.body.appendChild(notif);
            
            setTimeout(() => {
                notif.remove();
            }, 3000);
        }
        
        // Auto-imprimir si viene de venta procesada
        <?php if (isset($_SESSION['venta_procesada_id'])): ?>
        // Preguntar si desea imprimir
        if (confirm('¿Desea imprimir el recibo ahora?')) {
            window.print();
        }
        <?php 
        unset($_SESSION['venta_procesada_id']);
        unset($_SESSION['venta_procesada_codigo']);
        endif; 
        ?>
    </script>
</body>
</html>