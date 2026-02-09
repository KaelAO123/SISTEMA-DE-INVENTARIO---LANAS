<?php
require_once 'database.php';
require_once 'funciones.php';

Funciones::verificarSesion();

$db = getDB();
$venta = null;
$detalles = [];
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
    
    <!-- Bootstrap 5 (solo para pantalla) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome para íconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ===== ESTILOS BASE PARA TODO ===== */
        body {
            font-family: 'Courier New', monospace !important;
            background: white !important;
            color: black !important;
            margin: 0;
            padding: 10px;
        }
        
        /* Contenedor principal */
        .recibo-container {
            width: 80mm;
            max-width: 80mm;
            min-width: 80mm;
            margin: 0 auto;
            padding: 2mm;
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            border: 1px solid #ccc;
            page-break-inside: avoid;
        }
        
        /* Header */
        .header-recibo {
            text-align: center;
            padding-bottom: 3px;
            margin-bottom: 3px;
            border-bottom: 1px solid #000;
        }
        
        .empresa-nombre {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0;
            line-height: 1.1;
        }
        
        .empresa-desc {
            font-size: 8px;
            margin: 1px 0;
        }
        
        .titulo-recibo {
            font-size: 10px;
            font-weight: bold;
            margin: 2px 0;
        }
        
        .codigo-recibo {
            font-size: 9px;
            font-weight: bold;
        }
        
        /* Información general */
        .info-recibo {
            margin: 3px 0;
        }
        
        .info-linea {
            display: flex;
            justify-content: space-between;
            margin: 1px 0;
            padding: 0;
            font-size: 8px;
        }
        
        .info-label {
            font-weight: bold;
            white-space: nowrap;
        }
        
        .info-value {
            text-align: right;
            max-width: 45mm;
            word-break: break-word;
        }
        
        /* Líneas divisorias */
        .linea-divisoria {
            border-top: 1px dashed #000;
            margin: 3px 0;
        }
        
        .linea-doble {
            border-top: 2px solid #000;
            margin: 4px 0;
        }
        
        .linea-separador {
            text-align: center;
            font-size: 8px;
            margin: 2px 0;
        }
        
        /* Tabla de productos */
        .tabla-productos {
            width: 100%;
            margin: 3px 0;
            border-collapse: collapse;
        }
        
        .tabla-productos thead th {
            border-bottom: 1px solid #000;
            padding: 1px 0;
            font-size: 7px;
            font-weight: bold;
            text-align: left;
        }
        
        .tabla-productos tbody td {
            padding: 1px 0;
            font-size: 7px;
            border-bottom: 1px dotted #ccc;
            vertical-align: top;
        }
        
        .texto-derecha {
            text-align: right;
        }
        
        .texto-centro {
            text-align: center;
        }
        
        /* Totales */
        .seccion-totales {
            margin: 4px 0;
            padding-top: 3px;
        }
        
        .linea-total {
            display: flex;
            justify-content: space-between;
            margin: 1px 0;
            padding: 1px 0;
            font-size: 8px;
        }
        
        .total-final {
            font-size: 9px;
            font-weight: bold;
            border-top: 1px solid #000;
            margin-top: 2px;
            padding-top: 2px;
        }
        
        /* Estado */
        .estado-recibo {
            border: 1px solid #000;
            padding: 0 4px;
            font-weight: bold;
            font-size: 8px;
            background: white !important;
            color: black !important;
        }
        
        /* Observaciones */
        .observaciones {
            margin: 3px 0;
            padding: 2px;
            font-size: 7px;
        }
        
        /* Footer */
        .footer-recibo {
            margin-top: 5px;
            padding-top: 3px;
            border-top: 1px dashed #000;
            text-align: center;
            font-size: 7px;
        }
        
        /* Firma */
        .firma-recibo {
            margin-top: 8px;
            text-align: center;
        }
        
        .linea-firma {
            border-top: 1px solid #000;
            width: 50mm;
            margin: 5px auto 0 auto;
            padding-top: 2px;
            font-size: 7px;
        }
        
        /* ===== ESTILOS ESPECÍFICOS PARA PANTALLA ===== */
        @media screen {
            body {
                background: #f5f5f5 !important;
                padding: 20px;
            }
            
            .recibo-container {
                box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                border: 1px solid #999;
            }
            
            /* Botones flotantes */
            .botones-accion {
                position: fixed;
                bottom: 30px;
                right: 30px;
                display: flex;
                flex-direction: column;
                gap: 15px;
                z-index: 1000;
            }
            
            .boton-accion {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                border: 2px solid #000;
                color: #000;
                font-size: 1.5rem;
                cursor: pointer;
                background: white;
                box-shadow: 0 3px 8px rgba(0,0,0,0.3);
                transition: all 0.3s;
                display: flex;
                align-items: center;
                justify-content: center;
                position: relative;
            }
            
            .boton-accion:hover {
                transform: translateY(-3px);
                box-shadow: 0 5px 12px rgba(0,0,0,0.4);
            }
            
            .boton-accion:hover::after {
                content: attr(title);
                position: absolute;
                right: 70px;
                top: 50%;
                transform: translateY(-50%);
                background: #000;
                color: white;
                padding: 6px 12px;
                border-radius: 4px;
                font-size: 12px;
                font-family: Arial, sans-serif;
                white-space: nowrap;
                z-index: 1001;
            }
            
            .boton-imprimir {
                border-color: #000;
            }
            
            .boton-pdf {
                border-color: #333;
            }
            
            .boton-volver {
                border-color: #666;
            }
            
            /* Notificaciones */
            .notificacion {
                position: fixed;
                top: 20px;
                left: 50%;
                transform: translateX(-50%);
                padding: 10px 20px;
                border-radius: 3px;
                background: #000;
                color: white;
                font-size: 12px;
                font-weight: bold;
                z-index: 9999;
                animation: mostrarNotificacion 0.3s ease-out;
                box-shadow: 0 3px 6px rgba(0,0,0,0.2);
                font-family: Arial, sans-serif;
            }
            
            @keyframes mostrarNotificacion {
                from {
                    transform: translate(-50%, -20px);
                    opacity: 0;
                }
                to {
                    transform: translate(-50%, 0);
                    opacity: 1;
                }
            }
        }
        
        /* ===== ESTILOS PARA IMPRESIÓN ===== */
        @media print {
            body {
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            
            .recibo-container {
                box-shadow: none !important;
                border: none !important;
                width: 80mm !important;
                margin: 0 auto !important;
            }
            
            .botones-accion {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <?php if ($error): ?>
        <div class="recibo-container">
            <div class="text-center py-4">
                <div class="linea-doble"></div>
                <div class="titulo-recibo">ERROR</div>
                <div class="linea-divisoria"></div>
                <div class="info-recibo">
                    <div class="info-linea">
                        <span class="info-label">Mensaje:</span>
                        <span class="info-value"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                </div>
                <div class="linea-doble"></div>
                <button class="boton-accion boton-volver mt-3" onclick="window.location.href='modulo_ventas.php'" title="Volver">
                    ←
                </button>
            </div>
        </div>
    <?php else: ?>
        <!-- RECIBO PRINCIPAL - MISMO FORMATO PARA TODO -->
        <div class="recibo-container" id="reciboContent">
            <!-- Header -->
            <div class="header-recibo">
                <div class="empresa-nombre">LANAS Y TEXTILES</div>
                <div class="empresa-desc">Tienda de Lanas y Materiales</div>
                <div class="linea-doble"></div>
                <div class="titulo-recibo">RECIBO DE VENTA</div>
                <div class="codigo-recibo">Código: <?php echo htmlspecialchars($venta['codigo_venta']); ?></div>
            </div>
            
            <!-- Información general -->
            <div class="info-recibo">
                <div class="info-linea">
                    <span class="info-label">FECHA/HORA:</span>
                    <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($venta['fecha_hora'])); ?></span>
                </div>
                
                <div class="info-linea">
                    <span class="info-label">CLIENTE:</span>
                    <span class="info-value"><?php echo htmlspecialchars($venta['cliente_nombre'] ?? 'CONSUMIDOR FINAL'); ?></span>
                </div>
                
                <?php if ($venta['cliente_telefono']): ?>
                <div class="info-linea">
                    <span class="info-label">TELÉFONO:</span>
                    <span class="info-value"><?php echo htmlspecialchars($venta['cliente_telefono']); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="info-linea">
                    <span class="info-label">VENDEDOR:</span>
                    <span class="info-value"><?php echo htmlspecialchars($venta['vendedor_nombre'] ?? 'N/A'); ?></span>
                </div>
                
                <div class="info-linea">
                    <span class="info-label">PAGO:</span>
                    <span class="info-value"><?php echo htmlspecialchars(strtoupper($venta['tipo_pago'])); ?></span>
                </div>
                
                <div class="info-linea">
                    <span class="info-label">ESTADO:</span>
                    <span class="info-value">
                        <span class="estado-recibo"><?php echo strtoupper($venta['estado']); ?></span>
                    </span>
                </div>
            </div>
            
            <div class="linea-divisoria"></div>
            
            <!-- Tabla de productos -->
            <div class="titulo-recibo">PRODUCTOS:</div>
            <table class="tabla-productos">
                <thead>
                    <tr>
                        <th>DESCRIPCIÓN</th>
                        <th class="texto-centro">CANT</th>
                        <th class="texto-derecha">TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalles as $detalle): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($detalle['nombre_color']); ?><br>
                                <span style="font-size: 6px;"><?php echo htmlspecialchars($detalle['codigo_color']); ?></span>
                            </td>
                            <td class="texto-centro"><?php echo $detalle['cantidad']; ?></td>
                            <td class="texto-derecha"><?php echo Funciones::formatearMonedaBolivianos($detalle['subtotal']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="linea-divisoria"></div>
            
            <!-- Totales -->
            <div class="seccion-totales">
                <div class="linea-total">
                    <span>SUBTOTAL NETO:</span>
                    <span><?php echo Funciones::formatearMonedaBolivianos($venta['subtotal']); ?></span>
                </div>
                
                <?php if ($venta['descuento'] > 0): ?>
                <div class="linea-total">
                    <span>DESCUENTO:</span>
                    <span>-<?php echo Funciones::formatearMonedaBolivianos($venta['descuento']); ?></span>
                </div>
                <?php endif; ?>
                
                
                <div class="linea-separador">-------------------</div>
                
                <div class="linea-total total-final">
                    <span>TOTAL A PAGAR:</span>
                    <span><?php echo Funciones::formatearMonedaBolivianos($venta['total']); ?></span>
                </div>
                
                <?php if ($venta['debe'] > 0): ?>
                <div class="linea-separador">-------------------</div>
                
                <div class="linea-total">
                    <span>PAGADO:</span>
                    <span><?php echo Funciones::formatearMonedaBolivianos($venta['pagado']); ?></span>
                </div>
                
                <div class="linea-total">
                    <span><strong>SALDO PENDIENTE:</strong></span>
                    <span><strong><?php echo Funciones::formatearMonedaBolivianos($venta['debe']); ?></strong></span>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($venta['observaciones']): ?>
            <div class="linea-divisoria"></div>
            <div class="observaciones">
                <div class="info-label">OBSERVACIONES:</div>
                <div><?php echo htmlspecialchars($venta['observaciones']); ?></div>
            </div>
            <?php endif; ?>
            
            <div class="linea-doble"></div>
            
            <!-- Footer -->
            <div class="footer-recibo">
                <div><strong>¡GRACIAS POR SU COMPRA!</strong></div>
                <div>Conserve este recibo para cualquier reclamo</div>
                <div class="linea-separador">---</div>
                <div>Impreso: <?php echo date('d/m/Y H:i:s'); ?></div>
            </div>
            
            <!-- Firma -->
            <div class="firma-recibo">
                <div class="linea-firma">FIRMA DEL CLIENTE</div>
            </div>
            
            <!-- Información adicional (solo visible en PDF) -->
            <div style="display: none; font-size: 6px; text-align: center; margin-top: 5px; border-top: 1px dotted #ccc; padding-top: 2px;" id="infoPDF">
                Recibo generado por Sistema de Ventas - Lanas y Textiles
            </div>
        </div>
        
        <!-- Botones de acción (solo en pantalla) -->
        <div class="botones-accion">
            <button class="boton-accion boton-imprimir" onclick="imprimirRecibo()" title="Imprimir Recibo">
                <i class="fas fa-print"></i>
            </button>
            
            <button class="boton-accion boton-pdf" onclick="descargarPDF()" title="Descargar PDF">
                <i class="fas fa-file-pdf"></i>
            </button>
            
            <button class="boton-accion boton-volver" onclick="window.location.href='modulo_ventas.php'" title="Volver a Ventas">
                <i class="fas fa-arrow-left"></i>
            </button>
        </div>
    <?php endif; ?>
    
    <!-- jsPDF y html2canvas -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    
    <script>
        // Función para imprimir el recibo
        function imprimirRecibo() {
            // Mostrar información adicional para impresión
            const infoPDF = document.getElementById('infoPDF');
            infoPDF.style.display = 'block';
            
            // Esperar un momento para que se muestre la info
            setTimeout(() => {
                // Imprimir
                window.print();
                
                // Ocultar información adicional después de imprimir
                setTimeout(() => {
                    infoPDF.style.display = 'none';
                }, 500);
                
                // Mostrar notificación
                mostrarNotificacion('Enviando a impresión...');
            }, 100);
        }
        
        // Función para descargar PDF con el mismo diseño
        async function descargarPDF() {
            try {
                // Mostrar notificación
                mostrarNotificacion('Generando PDF...');
                
                // Mostrar información adicional para PDF
                const infoPDF = document.getElementById('infoPDF');
                infoPDF.style.display = 'block';
                
                // Obtener el elemento del recibo
                const reciboElement = document.getElementById('reciboContent');
                
                // Calcular dimensiones para 80mm
                const widthMM = 80;
                const heightMM = reciboElement.offsetHeight * 0.264583; // Convertir px a mm
                
                // Configurar html2canvas
                const canvas = await html2canvas(reciboElement, {
                    scale: 3, // Alta resolución
                    logging: false,
                    backgroundColor: '#ffffff',
                    width: reciboElement.offsetWidth,
                    height: reciboElement.offsetHeight,
                    useCORS: true,
                    allowTaint: true
                });
                
                // Crear PDF con dimensiones exactas
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF({
                    orientation: 'portrait',
                    unit: 'mm',
                    format: [widthMM, heightMM + 5] // +5mm para margen
                });
                
                // Agregar la imagen al PDF manteniendo proporciones
                const imgData = canvas.toDataURL('image/png');
                pdf.addImage(imgData, 'PNG', 0, 0, widthMM, heightMM);
                
                // Descargar el PDF
                const codigo = '<?php echo $venta['codigo_venta'] ?? 'recibo'; ?>';
                pdf.save(`Recibo_${codigo}_<?php echo date('Ymd_His'); ?>.pdf`);
                
                // Ocultar información adicional
                infoPDF.style.display = 'none';
                
                // Mostrar notificación de éxito
                mostrarNotificacion('PDF descargado exitosamente');
                
            } catch (error) {
                console.error('Error generando PDF:', error);
                mostrarNotificacion('Error al generar PDF');
                
                // Asegurarse de ocultar información adicional
                const infoPDF = document.getElementById('infoPDF');
                infoPDF.style.display = 'none';
            }
        }
        
        // Función para mostrar notificaciones
        function mostrarNotificacion(mensaje) {
            // Crear elemento de notificación
            const notificacion = document.createElement('div');
            notificacion.className = 'notificacion';
            notificacion.textContent = mensaje;
            notificacion.style.fontFamily = 'Courier New, monospace';
            
            // Agregar al body
            document.body.appendChild(notificacion);
            
            // Remover después de 2 segundos
            setTimeout(() => {
                if (notificacion.parentNode) {
                    notificacion.parentNode.removeChild(notificacion);
                }
            }, 2000);
        }
        
        // Auto-imprimir si viene de venta procesada
        <?php if (isset($_SESSION['venta_procesada_id']) && $_SESSION['venta_procesada_id'] == $venta_id): ?>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                if (confirm('¿Desea imprimir el recibo ahora?')) {
                    imprimirRecibo();
                }
            }, 500);
        });
        <?php 
        unset($_SESSION['venta_procesada_id']);
        unset($_SESSION['venta_procesada_codigo']);
        endif; 
        ?>
        
        // Detectar tecla Ctrl+P para imprimir
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                imprimirRecibo();
            }
        });
    </script>
</body>
</html>