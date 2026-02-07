<?php
// modulo_reportes.php - Reportes y estadísticas mejorado

require_once 'database.php';
require_once 'funciones.php';

// Verificar sesión
Funciones::verificarSesion();

$db = getDB();

// Parámetros de filtro con validación
$filtro_tipo = $_GET['tipo'] ?? 'ventas';
$filtro_fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01');
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$filtro_vendedor = $_GET['vendedor'] ?? 'todos';
$filtro_cliente = $_GET['cliente'] ?? 'todos';
$filtro_producto = $_GET['producto'] ?? 'todos';
$filtro_proveedor = $_GET['proveedor'] ?? 'todos';
$filtro_categoria = $_GET['categoria'] ?? 'todos';

// Validar fechas
if (!strtotime($filtro_fecha_desde)) $filtro_fecha_desde = date('Y-m-01');
if (!strtotime($filtro_fecha_hasta)) $filtro_fecha_hasta = date('Y-m-d');

// Procesar exportación
if (isset($_GET['exportar'])) {
    $formato = $_GET['formato'] ?? 'csv';
    $tipo_reporte = $_GET['tipo_reporte'] ?? 'ventas';
    
    // Exportar según formato
    if ($formato === 'csv') {
        exportarCSV($db, $filtro_tipo, $filtro_fecha_desde, $filtro_fecha_hasta, $filtro_vendedor, $filtro_cliente, $filtro_producto, $filtro_categoria, $filtro_proveedor);
        exit;
    } elseif ($formato === 'excel') {
        exportarExcel($db, $filtro_tipo, $filtro_fecha_desde, $filtro_fecha_hasta, $filtro_vendedor, $filtro_cliente, $filtro_producto, $filtro_categoria, $filtro_proveedor);
        exit;
    } else {
        $mensaje = "Exportando reporte de {$tipo_reporte} en formato " . strtoupper($formato);
        $tipo_mensaje = 'info';
    }
}

// Obtener datos para filtros
try {
    // Vendedores
    $stmt = $db->query("SELECT id, nombre FROM usuarios WHERE estado = 'activo' ORDER BY nombre");
    $vendedores = $stmt->fetchAll();
    
    // Clientes
    $stmt = $db->query("SELECT id, nombre FROM clientes WHERE activo = TRUE ORDER BY nombre");
    $clientes = $stmt->fetchAll();
    
    // Productos
    $stmt = $db->query("SELECT id, nombre_color, codigo_color FROM subpaquetes WHERE activo = TRUE ORDER BY nombre_color");
    $productos = $stmt->fetchAll();
    
    // Proveedores
    $stmt = $db->query("SELECT id, nombre FROM proveedores WHERE activo = TRUE ORDER BY nombre");
    $proveedores = $stmt->fetchAll();
    
    // Categorías
    $stmt = $db->query("SELECT id, nombre FROM categorias ORDER BY nombre");
    $categorias = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Error al cargar filtros: " . $e->getMessage();
    $vendedores = $clientes = $productos = $proveedores = $categorias = [];
}

// Generar reporte según tipo
try {
    switch ($filtro_tipo) {
        case 'ventas':
            $reporte = generarReporteVentas($db, $filtro_fecha_desde, $filtro_fecha_hasta, $filtro_vendedor, $filtro_cliente);
            break;
            
        case 'productos':
            $reporte = generarReporteProductos($db, $filtro_fecha_desde, $filtro_fecha_hasta, $filtro_producto, $filtro_categoria);
            break;
            
        case 'vendedores':
            $reporte = generarReporteVendedores($db, $filtro_fecha_desde, $filtro_fecha_hasta, $filtro_vendedor);
            break;
            
        case 'clientes':
            $reporte = generarReporteClientes($db, $filtro_fecha_desde, $filtro_fecha_hasta, $filtro_cliente);
            break;
            
        case 'inventario':
            $reporte = generarReporteInventario($db, $filtro_producto, $filtro_categoria, $filtro_proveedor);
            break;
            
        case 'proveedores':
            $reporte = generarReporteProveedores($db);
            break;
            
        case 'cobranzas':
            $reporte = generarReporteCobranzas($db, $filtro_fecha_desde, $filtro_fecha_hasta, $filtro_cliente);
            break;
            
        default:
            $reporte = [];
    }
    
    // Estadísticas generales
    $estadisticas = obtenerEstadisticasGenerales($db, $filtro_fecha_desde, $filtro_fecha_hasta);
    
    // Productos más vendidos
    $top_productos = obtenerTopProductos($db, $filtro_fecha_desde, $filtro_fecha_hasta);
    
    // Mejores clientes
    $top_clientes = obtenerTopClientes($db, $filtro_fecha_desde, $filtro_fecha_hasta);
    
    // Ventas por día de la semana
    $ventas_semana = obtenerVentasPorDiaSemana($db, $filtro_fecha_desde, $filtro_fecha_hasta);
    
    // Productos con stock crítico
    $stock_critico = obtenerStockCritico($db);
    
} catch (PDOException $e) {
    $error = "Error al generar reporte: " . $e->getMessage();
    $reporte = [];
    $estadisticas = [
        'total_ventas' => 0, 
        'total_ingresos' => 0, 
        'total_cobrado' => 0, 
        'total_pendiente' => 0, 
        'promedio_venta' => 0,
        'clientes_unicos' => 0
    ];
    $top_productos = $top_clientes = $ventas_semana = $stock_critico = [];
}

// ================================================
// FUNCIONES DE REPORTES
// ================================================

function generarReporteVentas($db, $fecha_desde, $fecha_hasta, $vendedor, $cliente) {
    $sql = "SELECT 
                DATE(v.fecha_hora) as fecha,
                COUNT(*) as cantidad_ventas,
                COALESCE(SUM(v.total), 0) as total_ventas,
                COALESCE(SUM(v.pagado), 0) as total_pagado,
                COALESCE(SUM(v.debe), 0) as total_debe,
                COALESCE(AVG(v.total), 0) as promedio_venta,
                COUNT(DISTINCT v.cliente_id) as clientes_unicos,
                SUM(CASE WHEN v.estado = 'pagada' THEN 1 ELSE 0 END) as ventas_pagadas,
                SUM(CASE WHEN v.estado = 'pendiente' THEN 1 ELSE 0 END) as ventas_pendientes
            FROM ventas v
            WHERE DATE(v.fecha_hora) BETWEEN ? AND ?
            AND v.anulado = FALSE";
    
    $params = [$fecha_desde, $fecha_hasta];
    
    if ($vendedor !== 'todos') {
        $sql .= " AND v.vendedor_id = ?";
        $params[] = $vendedor;
    }
    
    if ($cliente !== 'todos') {
        $sql .= " AND v.cliente_id = ?";
        $params[] = $cliente;
    }
    
    $sql .= " GROUP BY DATE(v.fecha_hora) ORDER BY fecha DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function generarReporteProductos($db, $fecha_desde, $fecha_hasta, $producto, $categoria) {
    $sql = "SELECT 
                sp.nombre_color as producto,
                sp.codigo_color,
                p.nombre as paquete,
                c.nombre as categoria,
                pr.nombre as proveedor,
                COUNT(vd.id) as cantidad_ventas,
                COALESCE(SUM(vd.cantidad), 0) as unidades_vendidas,
                COALESCE(SUM(vd.subtotal), 0) as total_vendido,
                COALESCE(AVG(vd.precio_unitario), 0) as precio_promedio,
                MAX(v.fecha_hora) as ultima_venta,
                COALESCE(sp.stock, 0) as stock,
                COALESCE(sp.min_stock, 0) as min_stock
            FROM venta_detalles vd
            JOIN subpaquetes sp ON vd.subpaquete_id = sp.id
            JOIN paquetes p ON sp.paquete_id = p.id
            LEFT JOIN categorias c ON p.categoria_id = c.id
            LEFT JOIN proveedores pr ON p.proveedor_id = pr.id
            JOIN ventas v ON vd.venta_id = v.id
            WHERE DATE(v.fecha_hora) BETWEEN ? AND ?
            AND v.anulado = FALSE";
    
    $params = [$fecha_desde, $fecha_hasta];
    
    if ($producto !== 'todos') {
        $sql .= " AND vd.subpaquete_id = ?";
        $params[] = $producto;
    }
    
    if ($categoria !== 'todos') {
        $sql .= " AND p.categoria_id = ?";
        $params[] = $categoria;
    }
    
    $sql .= " GROUP BY vd.subpaquete_id 
             ORDER BY total_vendido DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function generarReporteVendedores($db, $fecha_desde, $fecha_hasta, $vendedor) {
    $sql = "SELECT 
                u.nombre as vendedor,
                u.email,
                COUNT(v.id) as ventas_realizadas,
                COALESCE(SUM(v.total), 0) as total_vendido,
                COALESCE(AVG(v.total), 0) as promedio_venta,
                COALESCE(SUM(v.pagado), 0) as total_cobrado,
                COALESCE(SUM(v.debe), 0) as total_pendiente,
                COUNT(DISTINCT v.cliente_id) as clientes_atendidos,
                MIN(v.fecha_hora) as primera_venta,
                MAX(v.fecha_hora) as ultima_venta,
                SUM(CASE WHEN v.tipo_pago = 'contado' THEN 1 ELSE 0 END) as ventas_contado,
                SUM(CASE WHEN v.tipo_pago = 'credito' THEN 1 ELSE 0 END) as ventas_credito
            FROM ventas v
            JOIN usuarios u ON v.vendedor_id = u.id
            WHERE DATE(v.fecha_hora) BETWEEN ? AND ?
            AND v.anulado = FALSE";
    
    $params = [$fecha_desde, $fecha_hasta];
    
    if ($vendedor !== 'todos') {
        $sql .= " AND v.vendedor_id = ?";
        $params[] = $vendedor;
    }
    
    $sql .= " GROUP BY v.vendedor_id 
             ORDER BY total_vendido DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function generarReporteClientes($db, $fecha_desde, $fecha_hasta, $cliente) {
    $sql = "SELECT 
                c.nombre as cliente,
                c.id as cliente_id,
                c.tipo_documento,
                c.numero_documento,
                c.telefono,
                COUNT(v.id) as compras_realizadas,
                COALESCE(SUM(v.total), 0) as total_comprado,
                COALESCE(AVG(v.total), 0) as promedio_compra,
                MAX(v.fecha_hora) as ultima_compra,
                MIN(v.fecha_hora) as primera_compra,
                COALESCE(c.saldo_deuda, 0) as saldo_deuda,
                COALESCE(c.limite_credito, 0) as limite_credito,
                c.historial_compras,
                (COALESCE(c.saldo_deuda, 0) / NULLIF(COALESCE(c.limite_credito, 1), 0) * 100) as porcentaje_credito,
                COUNT(DISTINCT v.vendedor_id) as vendedores_atendidos
            FROM ventas v
            JOIN clientes c ON v.cliente_id = c.id
            WHERE DATE(v.fecha_hora) BETWEEN ? AND ?
            AND v.anulado = FALSE";
    
    $params = [$fecha_desde, $fecha_hasta];
    
    if ($cliente !== 'todos') {
        $sql .= " AND v.cliente_id = ?";
        $params[] = $cliente;
    }
    
    $sql .= " GROUP BY v.cliente_id 
             ORDER BY total_comprado DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function generarReporteInventario($db, $producto, $categoria, $proveedor) {
    $sql = "SELECT 
                sp.id,
                sp.nombre_color as producto,
                sp.codigo_color,
                p.nombre as paquete,
                COALESCE(c.nombre, 'Sin categoría') as categoria,
                COALESCE(pr.nombre, 'Sin proveedor') as proveedor,
                COALESCE(sp.stock, 0) as stock,
                COALESCE(sp.min_stock, 0) as min_stock,
                COALESCE(sp.max_stock, 0) as max_stock,
                COALESCE(sp.precio_venta, 0) as precio_venta,
                (COALESCE(sp.stock, 0) * COALESCE(sp.precio_venta, 0)) as valor_inventario,
                COALESCE(sp.vendido_total, 0) as vendido_total,
                sp.fecha_ultima_venta,
                CASE 
                    WHEN COALESCE(sp.stock, 0) <= COALESCE(sp.min_stock, 0) THEN 'CRÍTICO'
                    WHEN COALESCE(sp.stock, 0) <= COALESCE(sp.min_stock, 0) * 2 THEN 'BAJO'
                    WHEN COALESCE(sp.stock, 0) >= COALESCE(sp.max_stock, 0) * 0.8 THEN 'ALTO'
                    ELSE 'NORMAL'
                END as estado_stock,
                (COALESCE(sp.stock, 0) / NULLIF(COALESCE(sp.max_stock, 1), 0) * 100) as porcentaje_stock,
                p.ubicacion
            FROM subpaquetes sp
            JOIN paquetes p ON sp.paquete_id = p.id
            LEFT JOIN categorias c ON p.categoria_id = c.id
            LEFT JOIN proveedores pr ON p.proveedor_id = pr.id
            WHERE sp.activo = TRUE";
    
    $params = [];
    
    if ($producto !== 'todos') {
        $sql .= " AND sp.id = ?";
        $params[] = $producto;
    }
    
    if ($categoria !== 'todos') {
        $sql .= " AND p.categoria_id = ?";
        $params[] = $categoria;
    }
    
    if ($proveedor !== 'todos') {
        $sql .= " AND p.proveedor_id = ?";
        $params[] = $proveedor;
    }
    
    $sql .= " ORDER BY sp.stock ASC, valor_inventario DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function generarReporteProveedores($db) {
    $sql = "SELECT 
                p.id,
                p.nombre as proveedor,
                p.ruc,
                p.telefono,
                p.email,
                COUNT(DISTINCT pa.id) as productos_ofrecidos,
                COUNT(DISTINCT sp.id) as variantes,
                COALESCE(SUM(sp.stock), 0) as stock_total,
                COALESCE(SUM(sp.stock * sp.precio_venta), 0) as valor_inventario,
                COALESCE(p.saldo_deuda, 0) as saldo_deuda,
                (SELECT COUNT(*) FROM compras_proveedores cp WHERE cp.proveedor_id = p.id AND cp.estado = 'pendiente') as compras_pendientes,
                (SELECT MAX(fecha_compra) FROM compras_proveedores cp WHERE cp.proveedor_id = p.id) as ultima_compra
            FROM proveedores p
            LEFT JOIN paquetes pa ON p.id = pa.proveedor_id
            LEFT JOIN subpaquetes sp ON pa.id = sp.paquete_id AND sp.activo = TRUE
            GROUP BY p.id
            ORDER BY valor_inventario DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll();
}

function generarReporteCobranzas($db, $fecha_desde, $fecha_hasta, $cliente) {
    $sql = "SELECT 
                v.id,
                c.nombre as cliente,
                v.codigo_venta,
                COALESCE(v.total, 0) as monto_total,
                COALESCE(v.pagado, 0) as monto_pagado,
                COALESCE(v.debe, 0) as monto_pendiente,
                v.fecha_hora as fecha_venta,
                v.fecha_vencimiento,
                DATEDIFF(CURDATE(), COALESCE(v.fecha_vencimiento, CURDATE())) as dias_vencidos,
                CASE 
                    WHEN DATEDIFF(CURDATE(), COALESCE(v.fecha_vencimiento, CURDATE())) > 30 THEN 'VENCIDO'
                    WHEN DATEDIFF(CURDATE(), COALESCE(v.fecha_vencimiento, CURDATE())) > 15 THEN 'POR VENCER'
                    ELSE 'AL DÍA'
                END as estado_cobro,
                u.nombre as vendedor
            FROM ventas v
            JOIN clientes c ON v.cliente_id = c.id
            JOIN usuarios u ON v.vendedor_id = u.id
            WHERE v.anulado = FALSE
            AND v.debe > 0
            AND DATE(v.fecha_hora) BETWEEN ? AND ?";
    
    $params = [$fecha_desde, $fecha_hasta];
    
    if ($cliente !== 'todos') {
        $sql .= " AND v.cliente_id = ?";
        $params[] = $cliente;
    }
    
    $sql .= " ORDER BY v.fecha_vencimiento ASC, v.debe DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function obtenerEstadisticasGenerales($db, $fecha_desde, $fecha_hasta) {
    $sql = "SELECT 
                COUNT(*) as total_ventas,
                COALESCE(SUM(total), 0) as total_ingresos,
                COALESCE(SUM(pagado), 0) as total_cobrado,
                COALESCE(SUM(debe), 0) as total_pendiente,
                COALESCE(AVG(total), 0) as promedio_venta,
                COUNT(DISTINCT cliente_id) as clientes_unicos,
                COUNT(DISTINCT vendedor_id) as vendedores_activos,
                COALESCE(MIN(total), 0) as venta_minima,
                COALESCE(MAX(total), 0) as venta_maxima,
                COALESCE(SUM(CASE WHEN tipo_pago = 'contado' THEN total ELSE 0 END), 0) as ventas_contado,
                COALESCE(SUM(CASE WHEN tipo_pago = 'credito' THEN total ELSE 0 END), 0) as ventas_credito
            FROM ventas 
            WHERE DATE(fecha_hora) BETWEEN ? AND ?
            AND anulado = FALSE";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$fecha_desde, $fecha_hasta]);
    $result = $stmt->fetch();
    
    // Asegurar que todas las claves existan
    $estadisticas_default = [
        'total_ventas' => 0,
        'total_ingresos' => 0,
        'total_cobrado' => 0,
        'total_pendiente' => 0,
        'promedio_venta' => 0,
        'clientes_unicos' => 0,
        'vendedores_activos' => 0,
        'venta_minima' => 0,
        'venta_maxima' => 0,
        'ventas_contado' => 0,
        'ventas_credito' => 0
    ];
    
    return $result ? array_merge($estadisticas_default, $result) : $estadisticas_default;
}

function obtenerTopProductos($db, $fecha_desde, $fecha_hasta) {
    // Primero obtener el total general
    $sql_total = "SELECT 
                    COALESCE(SUM(vd.cantidad), 0) as total_general
                  FROM venta_detalles vd
                  JOIN ventas v ON vd.venta_id = v.id
                  WHERE DATE(v.fecha_hora) BETWEEN ? AND ?
                  AND v.anulado = FALSE";
    
    $stmt = $db->prepare($sql_total);
    $stmt->execute([$fecha_desde, $fecha_hasta]);
    $total_result = $stmt->fetch();
    $total_general = $total_result['total_general'] ?? 0;
    
    // Luego obtener los top productos - LIMIT como parte del SQL
    $sql = "SELECT 
                sp.nombre_color as producto,
                sp.codigo_color,
                COALESCE(SUM(vd.cantidad), 0) as cantidad_vendida,
                COALESCE(SUM(vd.subtotal), 0) as total_vendido
            FROM venta_detalles vd
            JOIN subpaquetes sp ON vd.subpaquete_id = sp.id
            JOIN ventas v ON vd.venta_id = v.id
            WHERE DATE(v.fecha_hora) BETWEEN ? AND ?
            AND v.anulado = FALSE
            GROUP BY vd.subpaquete_id
            ORDER BY cantidad_vendida DESC
            LIMIT 5";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$fecha_desde, $fecha_hasta]);
    $productos = $stmt->fetchAll();
    
    // Calcular porcentaje de participación
    foreach ($productos as &$producto) {
        $producto['porcentaje_participacion'] = $total_general > 0 ? ($producto['cantidad_vendida'] / $total_general * 100) : 0;
    }
    
    return $productos;
}

function obtenerTopClientes($db, $fecha_desde, $fecha_hasta) {
    // Primero obtener el total general
    $sql_total = "SELECT 
                    COALESCE(SUM(total), 0) as total_general
                  FROM ventas 
                  WHERE DATE(fecha_hora) BETWEEN ? AND ?
                  AND anulado = FALSE";
    
    $stmt = $db->prepare($sql_total);
    $stmt->execute([$fecha_desde, $fecha_hasta]);
    $total_result = $stmt->fetch();
    $total_general = $total_result['total_general'] ?? 0;
    
    // Luego obtener los top clientes - LIMIT como parte del SQL
    $sql = "SELECT 
                c.nombre as cliente,
                COUNT(v.id) as compras,
                COALESCE(SUM(v.total), 0) as total_comprado,
                COALESCE(AVG(v.total), 0) as promedio_compra,
                MAX(v.fecha_hora) as ultima_compra,
                COALESCE(c.saldo_deuda, 0) as saldo_deuda
            FROM ventas v
            JOIN clientes c ON v.cliente_id = c.id
            WHERE DATE(v.fecha_hora) BETWEEN ? AND ?
            AND v.anulado = FALSE
            GROUP BY v.cliente_id
            ORDER BY total_comprado DESC
            LIMIT 5";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$fecha_desde, $fecha_hasta]);
    $clientes = $stmt->fetchAll();
    
    // Calcular porcentaje de participación
    foreach ($clientes as &$cliente) {
        $cliente['porcentaje_participacion'] = $total_general > 0 ? ($cliente['total_comprado'] / $total_general * 100) : 0;
    }
    
    return $clientes;
}

function obtenerVentasPorDiaSemana($db, $fecha_desde, $fecha_hasta) {
    $sql = "SELECT 
                DAYNAME(fecha_hora) as dia_semana,
                DAYOFWEEK(fecha_hora) as numero_dia,
                COUNT(*) as cantidad_ventas,
                COALESCE(SUM(total), 0) as total_ventas,
                COALESCE(AVG(total), 0) as promedio_venta,
                COUNT(DISTINCT cliente_id) as clientes_unicos
            FROM ventas
            WHERE DATE(fecha_hora) BETWEEN ? AND ?
            AND anulado = FALSE
            GROUP BY DAYOFWEEK(fecha_hora), DAYNAME(fecha_hora)
            ORDER BY numero_dia";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$fecha_desde, $fecha_hasta]);
    return $stmt->fetchAll();
}

function obtenerStockCritico($db) {
    $sql = "SELECT 
                sp.id,
                sp.nombre_color as producto,
                sp.codigo_color,
                COALESCE(sp.stock, 0) as stock,
                COALESCE(sp.min_stock, 0) as min_stock,
                COALESCE(sp.max_stock, 0) as max_stock,
                (COALESCE(sp.stock, 0) / NULLIF(COALESCE(sp.min_stock, 1), 0)) as ratio_stock,
                p.nombre as paquete,
                COALESCE(pr.nombre, 'Sin proveedor') as proveedor,
                COALESCE(sp.precio_venta, 0) as precio_venta,
                (COALESCE(sp.stock, 0) * COALESCE(sp.precio_venta, 0)) as valor_stock
            FROM subpaquetes sp
            JOIN paquetes p ON sp.paquete_id = p.id
            LEFT JOIN proveedores pr ON p.proveedor_id = pr.id
            WHERE sp.activo = TRUE
            AND COALESCE(sp.stock, 0) <= COALESCE(sp.min_stock, 0)
            ORDER BY sp.stock ASC, ratio_stock ASC
            LIMIT 10";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll();
}

// ================================================
// FUNCIONES DE EXPORTACIÓN
// ================================================

function exportarCSV($db, $tipo, $fecha_desde, $fecha_hasta, $vendedor, $cliente, $producto, $categoria, $proveedor) {
    switch ($tipo) {
        case 'ventas':
            $datos = generarReporteVentas($db, $fecha_desde, $fecha_hasta, $vendedor, $cliente);
            $nombre_archivo = "reporte_ventas_" . date('Ymd_His') . ".csv";
            $columnas = ['Fecha', 'Cantidad Ventas', 'Total Ventas', 'Total Pagado', 'Total Debe', 'Promedio Venta', 'Clientes Únicos', 'Ventas Pagadas', 'Ventas Pendientes'];
            break;
        case 'productos':
            $datos = generarReporteProductos($db, $fecha_desde, $fecha_hasta, $producto, $categoria);
            $nombre_archivo = "reporte_productos_" . date('Ymd_His') . ".csv";
            $columnas = ['Producto', 'Código', 'Paquete', 'Categoría', 'Proveedor', 'Ventas', 'Unidades', 'Total Vendido', 'Precio Promedio', 'Última Venta', 'Stock', 'Stock Mínimo'];
            break;
        case 'clientes':
            $datos = generarReporteClientes($db, $fecha_desde, $fecha_hasta, $cliente);
            $nombre_archivo = "reporte_clientes_" . date('Ymd_His') . ".csv";
            $columnas = ['Cliente', 'Tipo Documento', 'Número Documento', 'Teléfono', 'Compras', 'Total Comprado', 'Promedio Compra', 'Última Compra', 'Primera Compra', 'Saldo Deuda', 'Límite Crédito', 'Porcentaje Crédito'];
            break;
        case 'inventario':
            $datos = generarReporteInventario($db, $producto, $categoria, $proveedor);
            $nombre_archivo = "reporte_inventario_" . date('Ymd_His') . ".csv";
            $columnas = ['Producto', 'Código', 'Paquete', 'Categoría', 'Proveedor', 'Stock', 'Mínimo', 'Máximo', 'Precio', 'Valor Inventario', 'Vendido Total', 'Última Venta', 'Estado Stock', 'Porcentaje Stock', 'Ubicación'];
            break;
        case 'cobranzas':
            $datos = generarReporteCobranzas($db, $fecha_desde, $fecha_hasta, $cliente);
            $nombre_archivo = "reporte_cobranzas_" . date('Ymd_His') . ".csv";
            $columnas = ['Cliente', 'Venta', 'Monto Total', 'Pagado', 'Pendiente', 'Fecha Venta', 'Vencimiento', 'Días Vencidos', 'Estado', 'Vendedor'];
            break;
        case 'proveedores':
            $datos = generarReporteProveedores($db);
            $nombre_archivo = "reporte_proveedores_" . date('Ymd_His') . ".csv";
            $columnas = ['Proveedor', 'RUC', 'Teléfono', 'Email', 'Productos', 'Variantes', 'Stock Total', 'Valor Inventario', 'Saldo Deuda', 'Compras Pendientes', 'Última Compra'];
            break;
        default:
            $datos = [];
            $nombre_archivo = "reporte.csv";
            $columnas = [];
    }
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $nombre_archivo);
    
    $output = fopen('php://output', 'w');
    fputcsv($output, $columnas);
    
    foreach ($datos as $fila) {
        fputcsv($output, array_values($fila));
    }
    
    fclose($output);
    exit;
}

function exportarExcel($db, $tipo, $fecha_desde, $fecha_hasta, $vendedor, $cliente, $producto, $categoria, $proveedor) {
    switch ($tipo) {
        case 'ventas':
            $datos = generarReporteVentas($db, $fecha_desde, $fecha_hasta, $vendedor, $cliente);
            $nombre_archivo = "reporte_ventas_" . date('Ymd_His') . ".xls";
            $titulo = "Reporte de Ventas";
            break;
        case 'productos':
            $datos = generarReporteProductos($db, $fecha_desde, $fecha_hasta, $producto, $categoria);
            $nombre_archivo = "reporte_productos_" . date('Ymd_His') . ".xls";
            $titulo = "Reporte de Productos";
            break;
        case 'clientes':
            $datos = generarReporteClientes($db, $fecha_desde, $fecha_hasta, $cliente);
            $nombre_archivo = "reporte_clientes_" . date('Ymd_His') . ".xls";
            $titulo = "Reporte de Clientes";
            break;
        case 'inventario':
            $datos = generarReporteInventario($db, $producto, $categoria, $proveedor);
            $nombre_archivo = "reporte_inventario_" . date('Ymd_His') . ".xls";
            $titulo = "Reporte de Inventario";
            break;
        case 'cobranzas':
            $datos = generarReporteCobranzas($db, $fecha_desde, $fecha_hasta, $cliente);
            $nombre_archivo = "reporte_cobranzas_" . date('Ymd_His') . ".xls";
            $titulo = "Reporte de Cobranzas";
            break;
        case 'proveedores':
            $datos = generarReporteProveedores($db);
            $nombre_archivo = "reporte_proveedores_" . date('Ymd_His') . ".xls";
            $titulo = "Reporte de Proveedores";
            break;
        default:
            $datos = [];
            $nombre_archivo = "reporte.xls";
            $titulo = "Reporte";
    }
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename=' . $nombre_archivo);
    
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>';
    echo '<table border="1">';
    echo '<tr><th colspan="' . (count($datos[0] ?? []) ?: 1) . '">' . $titulo . '</th></tr>';
    
    if (!empty($datos)) {
        // Cabeceras
        echo '<tr>';
        foreach (array_keys($datos[0]) as $columna) {
            echo '<th>' . ucfirst(str_replace('_', ' ', $columna)) . '</th>';
        }
        echo '</tr>';
        
        // Datos
        foreach ($datos as $fila) {
            echo '<tr>';
            foreach ($fila as $valor) {
                echo '<td>' . htmlspecialchars($valor ?? '') . '</td>';
            }
            echo '</tr>';
        }
    }
    
    echo '</table>';
    echo '</body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Sistema de Inventario</title>
    
    <?php include 'header.php'; ?>
    
    <style>
        /* Estilos igual que antes... */
        .stats-card { transition: all 0.3s ease; border: none; border-radius: 15px; overflow: hidden; margin-bottom: 1.5rem; }
        .stats-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important; }
        .filters-card { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 15px; padding: 1.5rem; margin-bottom: 2rem; border: 1px solid #dee2e6; }
        .tab-content { background: white; border-radius: 0 0 15px 15px; padding: 1.5rem; border: 1px solid #dee2e6; border-top: none; }
        .nav-tabs { border-bottom: 2px solid #dee2e6; }
        .nav-tabs .nav-link { border: none; border-radius: 10px 10px 0 0; padding: 0.75rem 1.5rem; font-weight: 500; color: #495057; transition: all 0.3s; margin-right: 0.5rem; }
        .nav-tabs .nav-link:hover { background-color: #f8f9fa; border-color: transparent; }
        .nav-tabs .nav-link.active { color: #28a745; background-color: white; border-bottom: 3px solid #28a745; font-weight: 600; }
        .chart-container { position: relative; height: 300px; margin-bottom: 2rem; }
        .report-summary { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border-radius: 15px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3); }
        .summary-item { text-align: center; padding: 1rem; }
        .summary-value { font-size: 2rem; font-weight: bold; margin-bottom: 0.5rem; }
        .summary-label { font-size: 0.875rem; opacity: 0.9; }
        .trend-up { color: #28a745; font-weight: 600; }
        .trend-down { color: #dc3545; font-weight: 600; }
        .export-options { background: #f8f9fa; border-radius: 15px; padding: 1.5rem; margin-top: 2rem; border: 1px solid #dee2e6; }
        .table-report { font-size: 0.875rem; }
        .table-report th { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; border-top: none; }
        .progress-inventory { height: 20px; border-radius: 10px; margin: 0.5rem 0; }
        .inventory-critical { background-color: #dc3545; }
        .inventory-low { background-color: #ffc107; }
        .inventory-medium { background-color: #17a2b8; }
        .inventory-high { background-color: #28a745; }
        .top-list { max-height: 300px; overflow-y: auto; }
        .top-item { padding: 0.75rem; border-bottom: 1px solid #e9ecef; display: flex; justify-content: space-between; align-items: center; transition: background-color 0.2s; }
        .top-item:hover { background-color: #f8f9fa; }
        .top-item:last-child { border-bottom: none; }
        .rank { width: 30px; height: 30px; border-radius: 50%; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.875rem; margin-right: 1rem; }
        .rank-1 { background-color: #ffc107; }
        .rank-2 { background-color: #6c757d; }
        .rank-3 { background-color: #fd7e14; }
        .rank-default { background-color: #28a745; }
        .stat-card { border-left: 4px solid #28a745; padding-left: 1rem; }
        .stat-card-warning { border-left-color: #ffc107; }
        .stat-card-danger { border-left-color: #dc3545; }
        .stat-card-info { border-left-color: #17a2b8; }
        .quick-filter { background: white; border-radius: 10px; padding: 0.75rem; margin-bottom: 1rem; border: 1px solid #dee2e6; cursor: pointer; transition: all 0.3s; }
        .quick-filter:hover { transform: translateX(5px); border-color: #28a745; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .quick-filter.active { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border-color: #28a745; }
        .filter-presets { margin-bottom: 1.5rem; }
        .stat-badge { font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 50px; }
        .venta-status { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 0.5rem; }
        .status-pagada { background-color: #28a745; }
        .status-pendiente { background-color: #ffc107; }
        .status-vencida { background-color: #dc3545; }
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .info-tooltip { cursor: help; border-bottom: 1px dotted #6c757d; }
        .scrollable-table { max-height: 500px; overflow-y: auto; }
        .loading-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.8); display: none; justify-content: center; align-items: center; z-index: 9999; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="loading-overlay" id="loadingOverlay">
            <div class="text-center">
                <div class="spinner-border text-success" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <p class="mt-2 text-muted">Generando reporte...</p>
            </div>
        </div>
        
        <div class="container-fluid py-4">
            <!-- Encabezado -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-2 text-success">
                        <i class="fas fa-chart-pie me-2"></i>Reportes y Estadísticas
                    </h1>
                    <p class="text-muted">
                        Analice el desempeño del negocio y genere reportes detallados
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-success" onclick="generarReporteCompleto()" data-bs-toggle="tooltip" title="Generar reporte completo en PDF">
                        <i class="fas fa-file-export me-2"></i>Exportar Reporte
                    </button>
                    <button class="btn btn-outline-success" onclick="imprimirReporte()" data-bs-toggle="tooltip" title="Imprimir reporte actual">
                        <i class="fas fa-print me-2"></i>Imprimir
                    </button>
                    <button class="btn btn-outline-primary" onclick="actualizarReporte()" data-bs-toggle="tooltip" title="Actualizar datos">
                        <i class="fas fa-sync-alt me-2"></i>Actualizar
                    </button>
                </div>
            </div>
            
            <!-- Mostrar mensajes -->
            <?php if (isset($mensaje)): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'info-circle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($mensaje); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Filtros rápidos -->
            <div class="filter-presets">
                <div class="row g-2">
                    <div class="col-auto">
                        <div class="quick-filter <?php echo $filtro_tipo === 'ventas' ? 'active' : ''; ?>" onclick="cambiarTipoReporte('ventas')">
                            <i class="fas fa-shopping-cart me-2"></i>Ventas
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="quick-filter <?php echo $filtro_tipo === 'productos' ? 'active' : ''; ?>" onclick="cambiarTipoReporte('productos')">
                            <i class="fas fa-boxes me-2"></i>Productos
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="quick-filter <?php echo $filtro_tipo === 'clientes' ? 'active' : ''; ?>" onclick="cambiarTipoReporte('clientes')">
                            <i class="fas fa-users me-2"></i>Clientes
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="quick-filter <?php echo $filtro_tipo === 'inventario' ? 'active' : ''; ?>" onclick="cambiarTipoReporte('inventario')">
                            <i class="fas fa-warehouse me-2"></i>Inventario
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="quick-filter <?php echo $filtro_tipo === 'cobranzas' ? 'active' : ''; ?>" onclick="cambiarTipoReporte('cobranzas')">
                            <i class="fas fa-money-bill-wave me-2"></i>Cobranzas
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="quick-filter <?php echo $filtro_tipo === 'proveedores' ? 'active' : ''; ?>" onclick="cambiarTipoReporte('proveedores')">
                            <i class="fas fa-truck me-2"></i>Proveedores
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filtros avanzados -->
            <div class="card filters-card">
                <form method="GET" id="filtersForm">
                    <input type="hidden" name="tipo" value="<?php echo $filtro_tipo; ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Fecha Desde</label>
                            <input type="date" class="form-control" name="fecha_desde" 
                                   value="<?php echo $filtro_fecha_desde; ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha Hasta</label>
                            <input type="date" class="form-control" name="fecha_hasta" 
                                   value="<?php echo $filtro_fecha_hasta; ?>" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Vendedor</label>
                            <select class="form-select" name="vendedor">
                                <option value="todos">Todos los vendedores</option>
                                <?php foreach ($vendedores as $v): ?>
                                    <option value="<?php echo $v['id']; ?>" 
                                        <?php echo $filtro_vendedor == $v['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($v['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Cliente</label>
                            <select class="form-select" name="cliente">
                                <option value="todos">Todos los clientes</option>
                                <?php foreach ($clientes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" 
                                        <?php echo $filtro_cliente == $c['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-filter me-2"></i>Aplicar Filtros
                            </button>
                        </div>
                    </div>
                    
                    <!-- Filtros adicionales según tipo de reporte -->
                    <?php if ($filtro_tipo === 'productos' || $filtro_tipo === 'inventario'): ?>
                    <div class="row g-3 mt-3">
                        <div class="col-md-4">
                            <label class="form-label">Producto</label>
                            <select class="form-select" name="producto">
                                <option value="todos">Todos los productos</option>
                                <?php foreach ($productos as $p): ?>
                                    <option value="<?php echo $p['id']; ?>" 
                                        <?php echo $filtro_producto == $p['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($p['nombre_color']); ?> (<?php echo $p['codigo_color']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Categoría</label>
                            <select class="form-select" name="categoria">
                                <option value="todos">Todas las categorías</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" 
                                        <?php echo $filtro_categoria == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Proveedor</label>
                            <select class="form-select" name="proveedor">
                                <option value="todos">Todos los proveedores</option>
                                <?php foreach ($proveedores as $prov): ?>
                                    <option value="<?php echo $prov['id']; ?>" 
                                        <?php echo $filtro_proveedor == $prov['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($prov['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Resumen estadístico -->
            <div class="dashboard-grid">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="summary-value"><?php echo $estadisticas['total_ventas']; ?></div>
                                    <div class="summary-label">Ventas Totales</div>
                                </div>
                                <div class="text-success">
                                    <i class="fas fa-shopping-cart fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="summary-value"><?php echo Funciones::formatearMoneda($estadisticas['total_ingresos']); ?></div>
                                    <div class="summary-label">Ingresos Totales</div>
                                </div>
                                <div class="text-primary">
                                    <i class="fas fa-money-bill-wave fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="summary-value"><?php echo Funciones::formatearMoneda($estadisticas['total_cobrado']); ?></div>
                                    <div class="summary-label">Total Cobrado</div>
                                </div>
                                <div class="text-info">
                                    <i class="fas fa-check-circle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="stat-card stat-card-danger">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="summary-value"><?php echo Funciones::formatearMoneda($estadisticas['total_pendiente']); ?></div>
                                    <div class="summary-label">Por Cobrar</div>
                                </div>
                                <div class="text-danger">
                                    <i class="fas fa-exclamation-triangle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="stat-card stat-card-info">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="summary-value"><?php echo $estadisticas['clientes_unicos']; ?></div>
                                    <div class="summary-label">Clientes Únicos</div>
                                </div>
                                <div class="text-info">
                                    <i class="fas fa-user-friends fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="stat-card stat-card-warning">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="summary-value"><?php echo Funciones::formatearMoneda($estadisticas['promedio_venta']); ?></div>
                                    <div class="summary-label">Promedio por Venta</div>
                                </div>
                                <div class="text-warning">
                                    <i class="fas fa-chart-line fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pestañas de reportes -->
            <ul class="nav nav-tabs mb-3" id="reportTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $filtro_tipo === 'ventas' ? 'active' : ''; ?>" 
                            id="ventas-tab" data-bs-toggle="tab" data-bs-target="#ventas" 
                            type="button" role="tab" onclick="cambiarTipoReporte('ventas')">
                        <i class="fas fa-shopping-cart me-2"></i>Ventas
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $filtro_tipo === 'productos' ? 'active' : ''; ?>" 
                            id="productos-tab" data-bs-toggle="tab" data-bs-target="#productos" 
                            type="button" role="tab" onclick="cambiarTipoReporte('productos')">
                        <i class="fas fa-boxes me-2"></i>Productos
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $filtro_tipo === 'vendedores' ? 'active' : ''; ?>" 
                            id="vendedores-tab" data-bs-toggle="tab" data-bs-target="#vendedores" 
                            type="button" role="tab" onclick="cambiarTipoReporte('vendedores')">
                        <i class="fas fa-user-tie me-2"></i>Vendedores
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $filtro_tipo === 'clientes' ? 'active' : ''; ?>" 
                            id="clientes-tab" data-bs-toggle="tab" data-bs-target="#clientes" 
                            type="button" role="tab" onclick="cambiarTipoReporte('clientes')">
                        <i class="fas fa-users me-2"></i>Clientes
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $filtro_tipo === 'inventario' ? 'active' : ''; ?>" 
                            id="inventario-tab" data-bs-toggle="tab" data-bs-target="#inventario" 
                            type="button" role="tab" onclick="cambiarTipoReporte('inventario')">
                        <i class="fas fa-warehouse me-2"></i>Inventario
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $filtro_tipo === 'cobranzas' ? 'active' : ''; ?>" 
                            id="cobranzas-tab" data-bs-toggle="tab" data-bs-target="#cobranzas" 
                            type="button" role="tab" onclick="cambiarTipoReporte('cobranzas')">
                        <i class="fas fa-money-bill-wave me-2"></i>Cobranzas
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $filtro_tipo === 'proveedores' ? 'active' : ''; ?>" 
                            id="proveedores-tab" data-bs-toggle="tab" data-bs-target="#proveedores" 
                            type="button" role="tab" onclick="cambiarTipoReporte('proveedores')">
                        <i class="fas fa-truck me-2"></i>Proveedores
                    </button>
                </li>
            </ul>
            
            <!-- Contenido de pestañas -->
            <div class="tab-content" id="reportTabsContent">
                
                <!-- Pestaña Ventas -->
                <div class="tab-pane fade <?php echo $filtro_tipo === 'ventas' ? 'show active' : ''; ?>" 
                     id="ventas" role="tabpanel">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <i class="fas fa-chart-line me-2"></i>
                                        Reporte de Ventas por Fecha
                                    </h5>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#" onclick="cambiarTipoGrafico('line')">Gráfico de Líneas</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="cambiarTipoGrafico('bar')">Gráfico de Barras</a></li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="ventasChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card mb-4">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-trophy me-2"></i>
                                        Productos Más Vendidos
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="top-list">
                                        <?php if (empty($top_productos)): ?>
                                            <div class="text-center py-3 text-muted">
                                                <p>No hay datos</p>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($top_productos as $index => $producto): ?>
                                                <div class="top-item">
                                                    <div class="d-flex align-items-center">
                                                        <div class="rank rank-<?php echo $index + 1; ?>">
                                                            <?php echo $index + 1; ?>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold"><?php echo htmlspecialchars($producto['producto']); ?></div>
                                                            <small class="text-muted"><?php echo $producto['cantidad_vendida']; ?> unidades</small>
                                                        </div>
                                                    </div>
                                                    <div class="fw-bold text-success">
                                                        <?php echo Funciones::formatearMoneda($producto['total_vendido']); ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Ventas por día de la semana -->
                            <?php if (!empty($ventas_semana)): ?>
                            <div class="card">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-calendar-alt me-2"></i>
                                        Ventas por Día de la Semana
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="ventasSemanaChart"></canvas>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-table me-2"></i>
                                Detalle de Ventas
                            </h5>
                            <button class="btn btn-sm btn-success" onclick="exportarTablaVentas()">
                                <i class="fas fa-download me-1"></i> Exportar
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive scrollable-table">
                                <table class="table table-hover table-report" id="tablaVentas">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Ventas</th>
                                            <th>Clientes Únicos</th>
                                            <th>Total Ventas</th>
                                            <th>Total Cobrado</th>
                                            <th>Por Cobrar</th>
                                            <th>Promedio Venta</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($reporte)): ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-3 text-muted">
                                                    No hay datos para el período seleccionado
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($reporte as $index => $fila): 
                                                $porcentaje_pagado = $fila['total_ventas'] > 0 ? ($fila['total_pagado'] / $fila['total_ventas']) * 100 : 0;
                                                $estado_color = $porcentaje_pagado >= 90 ? 'success' : ($porcentaje_pagado >= 50 ? 'warning' : 'danger');
                                            ?>
                                                <tr>
                                                    <td class="fw-bold"><?php echo Funciones::formatearFecha($fila['fecha']); ?></td>
                                                    <td>
                                                        <span class="badge bg-primary"><?php echo $fila['cantidad_ventas']; ?></span>
                                                        <small class="text-muted d-block">Pagadas: <?php echo $fila['ventas_pagadas']; ?></small>
                                                    </td>
                                                    <td><?php echo $fila['clientes_unicos']; ?></td>
                                                    <td class="text-success fw-bold"><?php echo Funciones::formatearMoneda($fila['total_ventas']); ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="progress flex-grow-1 me-2" style="height: 5px;">
                                                                <div class="progress-bar bg-success" style="width: <?php echo $porcentaje_pagado; ?>%"></div>
                                                            </div>
                                                            <?php echo Funciones::formatearMoneda($fila['total_pagado']); ?>
                                                        </div>
                                                    </td>
                                                    <td class="text-danger fw-bold"><?php echo Funciones::formatearMoneda($fila['total_debe']); ?></td>
                                                    <td><?php echo Funciones::formatearMoneda($fila['promedio_venta']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $estado_color; ?>">
                                                            <?php echo number_format($porcentaje_pagado, 1); ?>% cobrado
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pestaña Productos -->
                <div class="tab-pane fade <?php echo $filtro_tipo === 'productos' ? 'show active' : ''; ?>" 
                     id="productos" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-chart-bar me-2"></i>
                                        Distribución de Ventas por Producto
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="productosChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-star me-2"></i>
                                        Ranking de Productos
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Producto</th>
                                                    <th>Unidades</th>
                                                    <th>Total Vendido</th>
                                                    <th>Participación</th>
                                                    <th>Stock</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($reporte)): ?>
                                                    <tr>
                                                        <td colspan="6" class="text-center py-3 text-muted">
                                                            No hay datos para el período seleccionado
                                                        </td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php 
                                                    $total_general = array_sum(array_column($reporte, 'total_vendido'));
                                                    foreach ($reporte as $index => $producto): 
                                                        $estado_stock = $producto['stock'] <= $producto['min_stock'] ? 'danger' : 
                                                                      ($producto['stock'] <= $producto['min_stock'] * 2 ? 'warning' : 'success');
                                                        $participacion = $total_general > 0 ? ($producto['total_vendido'] / $total_general) * 100 : 0;
                                                    ?>
                                                        <tr>
                                                            <td>
                                                                <span class="rank rank-<?php echo $index + 1 <= 3 ? $index + 1 : 'default'; ?>">
                                                                    <?php echo $index + 1; ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <div class="fw-bold"><?php echo htmlspecialchars($producto['producto']); ?></div>
                                                                <small class="text-muted"><?php echo $producto['codigo_color']; ?></small>
                                                            </td>
                                                            <td class="fw-bold"><?php echo $producto['unidades_vendidas']; ?></td>
                                                            <td class="text-success fw-bold"><?php echo Funciones::formatearMoneda($producto['total_vendido']); ?></td>
                                                            <td>
                                                                <?php if ($producto['total_vendido'] > 0): ?>
                                                                    <div class="progress" style="height: 20px;">
                                                                        <div class="progress-bar bg-success" style="width: <?php echo min($participacion, 100); ?>%">
                                                                            <?php echo number_format($participacion, 1); ?>%
                                                                        </div>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-<?php echo $estado_stock; ?>">
                                                                    <?php echo $producto['stock']; ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pestaña Clientes -->
                <div class="tab-pane fade <?php echo $filtro_tipo === 'clientes' ? 'show active' : ''; ?>" 
                     id="clientes" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-chart-bar me-2"></i>
                                        Top 10 Clientes
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="clientesChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-crown me-2"></i>
                                        Mejores Clientes
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="top-list">
                                        <?php if (empty($top_clientes)): ?>
                                            <div class="text-center py-3 text-muted">
                                                <p>No hay datos</p>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($top_clientes as $index => $cliente): ?>
                                                <div class="top-item">
                                                    <div class="d-flex align-items-center">
                                                        <div class="rank rank-<?php echo $index + 1; ?>">
                                                            <?php echo $index + 1; ?>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold"><?php echo htmlspecialchars($cliente['cliente']); ?></div>
                                                            <small class="text-muted"><?php echo $cliente['compras']; ?> compras</small>
                                                        </div>
                                                    </div>
                                                    <div class="fw-bold text-success">
                                                        <?php echo Funciones::formatearMoneda($cliente['total_comprado']); ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-address-book me-2"></i>
                                Lista de Clientes
                            </h5>
                            <button class="btn btn-sm btn-success" onclick="exportarTablaClientes()">
                                <i class="fas fa-download me-1"></i> Exportar
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive scrollable-table">
                                <table class="table table-hover" id="tablaClientes">
                                    <thead>
                                        <tr>
                                            <th>Cliente</th>
                                            <th>Documento</th>
                                            <th>Compras</th>
                                            <th>Total Comprado</th>
                                            <th>Promedio Compra</th>
                                            <th>Última Compra</th>
                                            <th>Límite Crédito</th>
                                            <th>Saldo Pendiente</th>
                                            <th>% Crédito Usado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($reporte)): ?>
                                            <tr>
                                                <td colspan="9" class="text-center py-3 text-muted">
                                                    No hay datos para el período seleccionado
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($reporte as $cliente): 
                                                $porcentaje_credito = $cliente['limite_credito'] > 0 ? ($cliente['saldo_deuda'] / $cliente['limite_credito']) * 100 : 0;
                                                $credito_color = $porcentaje_credito >= 90 ? 'danger' : ($porcentaje_credito >= 50 ? 'warning' : 'success');
                                            ?>
                                                <tr>
                                                    <td class="fw-bold"><?php echo htmlspecialchars($cliente['cliente']); ?></td>
                                                    <td>
                                                        <small><?php echo $cliente['tipo_documento']; ?></small><br>
                                                        <span class="text-muted"><?php echo $cliente['numero_documento']; ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-primary"><?php echo $cliente['compras_realizadas']; ?></span>
                                                    </td>
                                                    <td class="text-success fw-bold"><?php echo Funciones::formatearMoneda($cliente['total_comprado']); ?></td>
                                                    <td><?php echo Funciones::formatearMoneda($cliente['promedio_compra']); ?></td>
                                                    <td>
                                                        <?php if ($cliente['ultima_compra']): ?>
                                                            <?php echo Funciones::formatearFecha($cliente['ultima_compra'], 'd/m/Y'); ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Nunca</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo Funciones::formatearMoneda($cliente['limite_credito']); ?></td>
                                                    <td class="<?php echo $cliente['saldo_deuda'] > 0 ? 'text-danger fw-bold' : 'text-success'; ?>">
                                                        <?php echo Funciones::formatearMoneda($cliente['saldo_deuda']); ?>
                                                    </td>
                                                    <td>
                                                        <div class="progress" style="height: 20px;">
                                                            <div class="progress-bar bg-<?php echo $credito_color; ?>" 
                                                                 style="width: <?php echo min($porcentaje_credito, 100); ?>%">
                                                                <?php echo number_format($porcentaje_credito, 1); ?>%
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pestaña Inventario -->
                <div class="tab-pane fade <?php echo $filtro_tipo === 'inventario' ? 'show active' : ''; ?>" 
                     id="inventario" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-chart-pie me-2"></i>
                                        Estado del Inventario
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="inventarioChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-warning text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        Productos con Stock Crítico
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Producto</th>
                                                    <th>Stock Actual</th>
                                                    <th>Mínimo</th>
                                                    <th>Ratio</th>
                                                    <th>Valor Stock</th>
                                                    <th>Estado</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($stock_critico)): ?>
                                                    <tr>
                                                        <td colspan="6" class="text-center py-3 text-success">
                                                            <i class="fas fa-check-circle me-2"></i>
                                                            Todo el stock está en niveles óptimos
                                                        </td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($stock_critico as $producto): 
                                                        $ratio = $producto['stock'] / max(1, $producto['min_stock']);
                                                        $estado_class = $ratio <= 0.5 ? 'inventory-critical' : 
                                                                      ($ratio <= 1 ? 'inventory-low' : 'inventory-medium');
                                                    ?>
                                                        <tr>
                                                            <td>
                                                                <div class="fw-bold"><?php echo htmlspecialchars($producto['producto']); ?></div>
                                                                <small class="text-muted"><?php echo $producto['paquete']; ?></small>
                                                            </td>
                                                            <td class="fw-bold"><?php echo $producto['stock']; ?></td>
                                                            <td><?php echo $producto['min_stock']; ?></td>
                                                            <td>
                                                                <span class="badge bg-<?php echo $ratio <= 1 ? 'danger' : ($ratio <= 2 ? 'warning' : 'success'); ?>">
                                                                    <?php echo number_format($ratio, 2); ?>
                                                                </span>
                                                            </td>
                                                            <td class="text-success fw-bold"><?php echo Funciones::formatearMoneda($producto['valor_stock']); ?></td>
                                                            <td>
                                                                <div class="progress progress-inventory">
                                                                    <div class="progress-bar <?php echo $estado_class; ?>" 
                                                                         style="width: <?php echo min(($producto['stock'] / max(1, $producto['max_stock'])) * 100, 100); ?>%">
                                                                    </div>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-boxes me-2"></i>
                                Inventario Completo
                            </h5>
                            <button class="btn btn-sm btn-success" onclick="exportarTablaInventario()">
                                <i class="fas fa-download me-1"></i> Exportar
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive scrollable-table">
                                <table class="table table-hover" id="tablaInventario">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Código</th>
                                            <th>Categoría</th>
                                            <th>Proveedor</th>
                                            <th>Stock</th>
                                            <th>Mínimo</th>
                                            <th>Máximo</th>
                                            <th>Precio</th>
                                            <th>Valor Inventario</th>
                                            <th>Vendido Total</th>
                                            <th>Última Venta</th>
                                            <th>% Stock</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($reporte)): ?>
                                            <tr>
                                                <td colspan="13" class="text-center py-3 text-muted">
                                                    No hay productos en inventario
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($reporte as $producto): 
                                                $porcentaje = ($producto['stock'] / max(1, $producto['max_stock'])) * 100;
                                                $estado_class = $porcentaje <= 25 ? 'danger' : 
                                                              ($porcentaje <= 50 ? 'warning' : 'success');
                                                $estado_text = $producto['estado_stock'];
                                            ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($producto['producto']); ?></div>
                                                        <small class="text-muted"><?php echo $producto['paquete']; ?></small>
                                                    </td>
                                                    <td><?php echo $producto['codigo_color']; ?></td>
                                                    <td><?php echo htmlspecialchars($producto['categoria'] ?? 'N/A'); ?></td>
                                                    <td><?php echo htmlspecialchars($producto['proveedor'] ?? 'N/A'); ?></td>
                                                    <td class="fw-bold text-<?php echo $estado_class; ?>"><?php echo $producto['stock']; ?></td>
                                                    <td><?php echo $producto['min_stock']; ?></td>
                                                    <td><?php echo $producto['max_stock']; ?></td>
                                                    <td><?php echo Funciones::formatearMoneda($producto['precio_venta']); ?></td>
                                                    <td class="text-success fw-bold"><?php echo Funciones::formatearMoneda($producto['valor_inventario']); ?></td>
                                                    <td><?php echo $producto['vendido_total']; ?></td>
                                                    <td>
                                                        <?php if ($producto['fecha_ultima_venta']): ?>
                                                            <?php echo Funciones::formatearFecha($producto['fecha_ultima_venta']); ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Nunca</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="progress" style="height: 20px;">
                                                            <div class="progress-bar bg-<?php echo $estado_class; ?>" 
                                                                 style="width: <?php echo min($porcentaje, 100); ?>%">
                                                                <?php echo number_format($porcentaje, 1); ?>%
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo strtolower($estado_text) === 'crítico' ? 'danger' : 
                                                                               (strtolower($estado_text) === 'bajo' ? 'warning' : 
                                                                               (strtolower($estado_text) === 'alto' ? 'success' : 'info')); ?>">
                                                            <?php echo $estado_text; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pestaña Cobranzas -->
                <div class="tab-pane fade <?php echo $filtro_tipo === 'cobranzas' ? 'show active' : ''; ?>" 
                     id="cobranzas" role="tabpanel">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header bg-warning text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-chart-line me-2"></i>
                                        Estado de Cobranzas
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="cobranzasChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header bg-danger text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-exclamation-circle me-2"></i>
                                        Resumen de Morosidad
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php 
                                    $total_pendiente = 0;
                                    $vencido_30 = 0;
                                    $vencido_15 = 0;
                                    $al_dia = 0;
                                    
                                    if (!empty($reporte)) {
                                        foreach ($reporte as $cobranza) {
                                            $total_pendiente += $cobranza['monto_pendiente'];
                                            $dias = $cobranza['dias_vencidos'] ?? 0;
                                            if ($dias > 30) $vencido_30 += $cobranza['monto_pendiente'];
                                            elseif ($dias > 15) $vencido_15 += $cobranza['monto_pendiente'];
                                            else $al_dia += $cobranza['monto_pendiente'];
                                        }
                                    }
                                    ?>
                                    <div class="summary-item">
                                        <div class="summary-value"><?php echo Funciones::formatearMoneda($total_pendiente); ?></div>
                                        <div class="summary-label">Total Pendiente</div>
                                    </div>
                                    <div class="mt-3">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-danger">Vencido (>30 días)</span>
                                            <span class="fw-bold"><?php echo Funciones::formatearMoneda($vencido_30); ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-warning">Por Vencer (15-30 días)</span>
                                            <span class="fw-bold"><?php echo Funciones::formatearMoneda($vencido_15); ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-success">Al Día</span>
                                            <span class="fw-bold"><?php echo Funciones::formatearMoneda($al_dia); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-file-invoice-dollar me-2"></i>
                                Cuentas por Cobrar
                            </h5>
                            <button class="btn btn-sm btn-success" onclick="exportarTablaCobranzas()">
                                <i class="fas fa-download me-1"></i> Exportar
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive scrollable-table">
                                <table class="table table-hover" id="tablaCobranzas">
                                    <thead>
                                        <tr>
                                            <th>Cliente</th>
                                            <th>Venta</th>
                                            <th>Monto Total</th>
                                            <th>Pagado</th>
                                            <th>Pendiente</th>
                                            <th>Fecha Venta</th>
                                            <th>Vencimiento</th>
                                            <th>Días Vencidos</th>
                                            <th>Estado</th>
                                            <th>Vendedor</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($reporte)): ?>
                                            <tr>
                                                <td colspan="10" class="text-center py-3 text-success">
                                                    <i class="fas fa-check-circle me-2"></i>
                                                    No hay cuentas pendientes por cobrar
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($reporte as $cobranza): 
                                                $dias_vencidos = $cobranza['dias_vencidos'] ?? 0;
                                                $estado_color = $dias_vencidos > 30 ? 'danger' : 
                                                               ($dias_vencidos > 15 ? 'warning' : 'success');
                                            ?>
                                                <tr>
                                                    <td class="fw-bold"><?php echo htmlspecialchars($cobranza['cliente']); ?></td>
                                                    <td>
                                                        <span class="badge bg-info"><?php echo $cobranza['codigo_venta'] ?? 'N/A'; ?></span>
                                                    </td>
                                                    <td><?php echo Funciones::formatearMoneda($cobranza['monto_total']); ?></td>
                                                    <td class="text-success"><?php echo Funciones::formatearMoneda($cobranza['monto_pagado']); ?></td>
                                                    <td class="text-danger fw-bold"><?php echo Funciones::formatearMoneda($cobranza['monto_pendiente']); ?></td>
                                                    <td><?php echo Funciones::formatearFecha($cobranza['fecha_venta'], 'd/m/Y'); ?></td>
                                                    <td>
                                                        <?php if ($cobranza['fecha_vencimiento']): ?>
                                                            <?php echo Funciones::formatearFecha($cobranza['fecha_vencimiento'], 'd/m/Y'); ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Sin fecha</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $estado_color; ?>">
                                                            <?php echo $dias_vencidos > 0 ? $dias_vencidos : 0; ?> días
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $estado_color; ?>">
                                                            <?php echo $cobranza['estado_cobro'] ?? 'AL DÍA'; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($cobranza['vendedor'] ?? 'N/A'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pestaña Proveedores -->
                <div class="tab-pane fade <?php echo $filtro_tipo === 'proveedores' ? 'show active' : ''; ?>" 
                     id="proveedores" role="tabpanel">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-chart-pie me-2"></i>
                                        Distribución por Proveedor
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="proveedoresChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-medal me-2"></i>
                                        Top Proveedores
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="top-list">
                                        <?php if (empty($reporte)): ?>
                                            <div class="text-center py-3 text-muted">
                                                <p>No hay datos</p>
                                            </div>
                                        <?php else: ?>
                                            <?php $top_proveedores = array_slice($reporte, 0, 5); ?>
                                            <?php foreach ($top_proveedores as $index => $proveedor): ?>
                                                <div class="top-item">
                                                    <div class="d-flex align-items-center">
                                                        <div class="rank rank-<?php echo $index + 1; ?>">
                                                            <?php echo $index + 1; ?>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold"><?php echo htmlspecialchars($proveedor['proveedor']); ?></div>
                                                            <small class="text-muted"><?php echo $proveedor['productos_ofrecidos']; ?> productos</small>
                                                        </div>
                                                    </div>
                                                    <div class="fw-bold text-success">
                                                        <?php echo Funciones::formatearMoneda($proveedor['valor_inventario']); ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-truck me-2"></i>
                                Lista de Proveedores
                            </h5>
                            <button class="btn btn-sm btn-success" onclick="exportarTablaProveedores()">
                                <i class="fas fa-download me-1"></i> Exportar
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive scrollable-table">
                                <table class="table table-hover" id="tablaProveedores">
                                    <thead>
                                        <tr>
                                            <th>Proveedor</th>
                                            <th>RUC</th>
                                            <th>Contacto</th>
                                            <th>Productos</th>
                                            <th>Variantes</th>
                                            <th>Stock Total</th>
                                            <th>Valor Inventario</th>
                                            <th>Saldo Deuda</th>
                                            <th>Compras Pendientes</th>
                                            <th>Última Compra</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($reporte)): ?>
                                            <tr>
                                                <td colspan="10" class="text-center py-3 text-muted">
                                                    No hay proveedores registrados
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($reporte as $proveedor): 
                                                $deuda_color = $proveedor['saldo_deuda'] > 0 ? 'warning' : 'success';
                                            ?>
                                                <tr>
                                                    <td class="fw-bold"><?php echo htmlspecialchars($proveedor['proveedor']); ?></td>
                                                    <td><?php echo $proveedor['ruc']; ?></td>
                                                    <td>
                                                        <small><?php echo $proveedor['telefono']; ?></small><br>
                                                        <small class="text-muted"><?php echo $proveedor['email']; ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-primary"><?php echo $proveedor['productos_ofrecidos']; ?></span>
                                                    </td>
                                                    <td><?php echo $proveedor['variantes']; ?></td>
                                                    <td><?php echo $proveedor['stock_total']; ?></td>
                                                    <td class="text-success fw-bold"><?php echo Funciones::formatearMoneda($proveedor['valor_inventario']); ?></td>
                                                    <td class="text-<?php echo $deuda_color; ?> fw-bold">
                                                        <?php echo Funciones::formatearMoneda($proveedor['saldo_deuda']); ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($proveedor['compras_pendientes'] > 0): ?>
                                                            <span class="badge bg-warning"><?php echo $proveedor['compras_pendientes']; ?> pendientes</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success">Al día</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($proveedor['ultima_compra']): ?>
                                                            <?php echo Funciones::formatearFecha($proveedor['ultima_compra'], 'd/m/Y'); ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Nunca</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Opciones de exportación -->
            <div class="export-options">
                <h5 class="mb-3">
                    <i class="fas fa-download me-2"></i>Opciones de Exportación
                </h5>
                <form method="GET" class="row g-3">
                    <input type="hidden" name="tipo" value="<?php echo $filtro_tipo; ?>">
                    <input type="hidden" name="fecha_desde" value="<?php echo $filtro_fecha_desde; ?>">
                    <input type="hidden" name="fecha_hasta" value="<?php echo $filtro_fecha_hasta; ?>">
                    <input type="hidden" name="vendedor" value="<?php echo $filtro_vendedor; ?>">
                    <input type="hidden" name="cliente" value="<?php echo $filtro_cliente; ?>">
                    <input type="hidden" name="producto" value="<?php echo $filtro_producto; ?>">
                    <input type="hidden" name="categoria" value="<?php echo $filtro_categoria; ?>">
                    <input type="hidden" name="proveedor" value="<?php echo $filtro_proveedor; ?>">
                    
                    <div class="col-md-3">
                        <label class="form-label">Formato</label>
                        <select class="form-select" name="formato" id="formatoExportacion">
                            <option value="csv">CSV</option>
                            <option value="excel">Excel</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Tipo de Reporte</label>
                        <select class="form-select" name="tipo_reporte" id="tipoReporteExportacion">
                            <option value="<?php echo $filtro_tipo; ?>">Reporte Actual</option>
                            <option value="detallado">Reporte Detallado</option>
                            <option value="resumen">Resumen Ejecutivo</option>
                            <option value="completo">Reporte Completo</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" name="exportar" value="1" class="btn btn-success w-100">
                            <i class="fas fa-file-export me-2"></i>Exportar Reporte
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
    <script>
        // Datos para gráficos desde PHP
        const reporteData = <?php echo json_encode($reporte); ?>;
        const topProductos = <?php echo json_encode($top_productos); ?>;
        const topClientes = <?php echo json_encode($top_clientes); ?>;
        const ventasSemana = <?php echo json_encode($ventas_semana); ?>;
        const stockCritico = <?php echo json_encode($stock_critico); ?>;
        const tipoReporte = '<?php echo $filtro_tipo; ?>';
        const estadisticas = <?php echo json_encode($estadisticas); ?>;
        
        // Variables para gráficos
        let ventasChart = null;
        let productosChart = null;
        let clientesChart = null;
        let inventarioChart = null;
        let ventasSemanaChart = null;
        let cobranzasChart = null;
        let proveedoresChart = null;
        
        // Inicializar tooltips de Bootstrap
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Inicializar gráficos
            inicializarGraficos();
        });
        
        // Cambiar tipo de reporte
        function cambiarTipoReporte(tipo) {
            mostrarLoading();
            const url = new URL(window.location);
            url.searchParams.set('tipo', tipo);
            window.location = url.toString();
        }
        
        // Actualizar reporte
        function actualizarReporte() {
            mostrarLoading();
            document.getElementById('filtersForm').submit();
        }
        
        // Generar reporte completo
        function generarReporteCompleto() {
            mostrarLoading();
            const formato = document.getElementById('formatoExportacion').value;
            
            if (formato === 'pdf') {
                alert('La generación de PDF está en desarrollo. Se descargará en formato Excel.');
                // Aquí se implementaría la generación de PDF usando una librería como TCPDF o Dompdf
            }
            
            // Enviar formulario de exportación
            document.querySelector('.export-options form').submit();
        }
        
        // Imprimir reporte
        function imprimirReporte() {
            window.print();
        }
        
        // Exportar tabla específica
        function exportarTablaVentas() {
            exportarTabla('tablaVentas', 'reporte_ventas');
        }
        
        function exportarTablaClientes() {
            exportarTabla('tablaClientes', 'reporte_clientes');
        }
        
        function exportarTablaInventario() {
            exportarTabla('tablaInventario', 'reporte_inventario');
        }
        
        function exportarTablaCobranzas() {
            exportarTabla('tablaCobranzas', 'reporte_cobranzas');
        }
        
        function exportarTablaProveedores() {
            exportarTabla('tablaProveedores', 'reporte_proveedores');
        }
        
        function exportarTabla(idTabla, nombreArchivo) {
            const tabla = document.getElementById(idTabla);
            const wb = XLSX.utils.table_to_book(tabla);
            XLSX.writeFile(wb, `${nombreArchivo}_${new Date().toISOString().slice(0,10)}.xlsx`);
        }
        
        // Mostrar loading
        function mostrarLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }
        
        // Ocultar loading (se llama cuando la página carga)
        window.addEventListener('load', function() {
            document.getElementById('loadingOverlay').style.display = 'none';
        });
        
        // Inicializar gráficos
        function inicializarGraficos() {
            if (!reporteData || reporteData.length === 0) return;
            
            switch (tipoReporte) {
                case 'ventas':
                    inicializarGraficoVentas();
                    if (ventasSemana && ventasSemana.length > 0) {
                        inicializarGraficoVentasSemana();
                    }
                    break;
                case 'productos':
                    inicializarGraficoProductos();
                    break;
                case 'clientes':
                    inicializarGraficoClientes();
                    break;
                case 'inventario':
                    inicializarGraficoInventario();
                    break;
                case 'cobranzas':
                    inicializarGraficoCobranzas();
                    break;
                case 'proveedores':
                    inicializarGraficoProveedores();
                    break;
            }
        }
        
        // Gráfico de ventas
        function inicializarGraficoVentas() {
            const ctx = document.getElementById('ventasChart');
            if (!ctx) return;
            
            if (ventasChart) ventasChart.destroy();
            
            const fechas = reporteData.map(item => item.fecha).reverse();
            const ventas = reporteData.map(item => parseFloat(item.total_ventas) || 0).reverse();
            const cobrado = reporteData.map(item => parseFloat(item.total_pagado) || 0).reverse();
            const pendiente = reporteData.map(item => parseFloat(item.total_debe) || 0).reverse();
            
            ventasChart = new Chart(ctx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: fechas,
                    datasets: [
                        {
                            label: 'Total Ventas',
                            data: ventas,
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 2
                        },
                        {
                            label: 'Cobrado',
                            data: cobrado,
                            borderColor: '#007bff',
                            backgroundColor: 'rgba(0, 123, 255, 0.1)',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 2
                        },
                        {
                            label: 'Por Cobrar',
                            data: pendiente,
                            borderColor: '#dc3545',
                            backgroundColor: 'rgba(220, 53, 69, 0.1)',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': $' + context.raw.toLocaleString('es-EC', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString('es-EC');
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Gráfico de ventas por día de la semana
        function inicializarGraficoVentasSemana() {
            const ctx = document.getElementById('ventasSemanaChart');
            if (!ctx || !ventasSemana || ventasSemana.length === 0) return;
            
            if (ventasSemanaChart) ventasSemanaChart.destroy();
            
            const dias = ventasSemana.map(item => item.dia_semana);
            const ventas = ventasSemana.map(item => parseFloat(item.total_ventas) || 0);
            
            // Ordenar por número de día (lunes a domingo)
            const ordenDias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
            dias.sort((a, b) => ordenDias.indexOf(a) - ordenDias.indexOf(b));
            
            ventasSemanaChart = new Chart(ctx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: dias,
                    datasets: [{
                        label: 'Ventas por Día de la Semana',
                        data: ventas,
                        backgroundColor: [
                            '#28a745', '#20c997', '#17a2b8', '#007bff', '#6610f2',
                            '#6f42c1', '#e83e8c'
                        ],
                        borderColor: [
                            '#28a745', '#20c997', '#17a2b8', '#007bff', '#6610f2',
                            '#6f42c1', '#e83e8c'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString('es-EC');
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Gráfico de productos
        function inicializarGraficoProductos() {
            const ctx = document.getElementById('productosChart');
            if (!ctx) return;
            
            if (productosChart) productosChart.destroy();
            
            const productos = reporteData.slice(0, 10).map(item => 
                (item.producto || '').substring(0, 20) + ((item.producto || '').length > 20 ? '...' : '')
            );
            const ventas = reporteData.slice(0, 10).map(item => parseFloat(item.total_vendido) || 0);
            
            productosChart = new Chart(ctx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: productos,
                    datasets: [{
                        label: 'Total Vendido ($)',
                        data: ventas,
                        backgroundColor: 'rgba(40, 167, 69, 0.7)',
                        borderColor: 'rgb(40, 167, 69)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString('es-EC');
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Gráfico de clientes
        function inicializarGraficoClientes() {
            const ctx = document.getElementById('clientesChart');
            if (!ctx) return;
            
            if (clientesChart) clientesChart.destroy();
            
            const clientes = reporteData.slice(0, 10).map(item => 
                (item.cliente || '').substring(0, 15) + ((item.cliente || '').length > 15 ? '...' : '')
            );
            const compras = reporteData.slice(0, 10).map(item => parseFloat(item.total_comprado) || 0);
            
            clientesChart = new Chart(ctx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: clientes,
                    datasets: [{
                        label: 'Total Comprado ($)',
                        data: compras,
                        backgroundColor: 'rgba(40, 167, 69, 0.7)',
                        borderColor: 'rgb(40, 167, 69)',
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString('es-EC');
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Gráfico de inventario
        function inicializarGraficoInventario() {
            const ctx = document.getElementById('inventarioChart');
            if (!ctx) return;
            
            if (inventarioChart) inventarioChart.destroy();
            
            // Clasificar productos por estado de stock
            const critico = reporteData.filter(p => p.estado_stock === 'CRÍTICO').length;
            const bajo = reporteData.filter(p => p.estado_stock === 'BAJO').length;
            const normal = reporteData.filter(p => p.estado_stock === 'NORMAL').length;
            const alto = reporteData.filter(p => p.estado_stock === 'ALTO').length;
            
            inventarioChart = new Chart(ctx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Crítico', 'Bajo', 'Normal', 'Alto'],
                    datasets: [{
                        data: [critico, bajo, normal, alto],
                        backgroundColor: ['#dc3545', '#ffc107', '#17a2b8', '#28a745'],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} productos (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Gráfico de cobranzas
        function inicializarGraficoCobranzas() {
            const ctx = document.getElementById('cobranzasChart');
            if (!ctx) return;
            
            if (cobranzasChart) cobranzasChart.destroy();
            
            // Agrupar por estado de cobro
            const estados = {};
            reporteData.forEach(item => {
                const estado = item.estado_cobro || 'AL DÍA';
                estados[estado] = (estados[estado] || 0) + (parseFloat(item.monto_pendiente) || 0);
            });
            
            cobranzasChart = new Chart(ctx.getContext('2d'), {
                type: 'pie',
                data: {
                    labels: Object.keys(estados),
                    datasets: [{
                        data: Object.values(estados),
                        backgroundColor: ['#dc3545', '#ffc107', '#28a745'],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: $${value.toLocaleString('es-EC')} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Gráfico de proveedores
        function inicializarGraficoProveedores() {
            const ctx = document.getElementById('proveedoresChart');
            if (!ctx) return;
            
            if (proveedoresChart) proveedoresChart.destroy();
            
            const proveedores = reporteData.slice(0, 8).map(item => item.proveedor || 'N/A');
            const valores = reporteData.slice(0, 8).map(item => parseFloat(item.valor_inventario) || 0);
            
            proveedoresChart = new Chart(ctx.getContext('2d'), {
                type: 'pie',
                data: {
                    labels: proveedores,
                    datasets: [{
                        data: valores,
                        backgroundColor: [
                            '#28a745', '#20c997', '#17a2b8', '#007bff', 
                            '#6610f2', '#6f42c1', '#e83e8c', '#dc3545'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: $${value.toLocaleString('es-EC')} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Cambiar tipo de gráfico
        function cambiarTipoGrafico(tipo) {
            if (ventasChart) {
                ventasChart.config.type = tipo;
                ventasChart.update();
            }
        }
        
        // Redimensionar gráficos al cambiar tamaño de ventana
        window.addEventListener('resize', function() {
            if (ventasChart) ventasChart.resize();
            if (productosChart) productosChart.resize();
            if (clientesChart) clientesChart.resize();
            if (inventarioChart) inventarioChart.resize();
            if (ventasSemanaChart) ventasSemanaChart.resize();
            if (cobranzasChart) cobranzasChart.resize();
            if (proveedoresChart) proveedoresChart.resize();
        });
    </script>
    
    <!-- Script para exportar a Excel -->
    <script src="https://unpkg.com/xlsx/dist/xlsx.full.min.js"></script>
</body>
</html>