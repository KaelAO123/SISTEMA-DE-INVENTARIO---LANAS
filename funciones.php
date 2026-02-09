<?php
require_once 'database.php';

class Funciones {
    
    public static function verificarSesion() {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: index.php');
            exit();
        }
    }
    
    public static function esAdmin() {
        return isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
    }
    
    public static function esVendedor() {
        return isset($_SESSION['rol']) && $_SESSION['rol'] === 'vendedor';
    }
    
    public static function sanitizar($input) {
        $input = trim($input);
        $input = stripslashes($input);
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        return $input;
    }
    
    public static function formatearFecha($fecha, $formato = 'd/m/Y') {
        if (empty($fecha)) return '';
        $timestamp = strtotime($fecha);
        return date($formato, $timestamp);
    }
    
    public static function formatearMonedaBolivianos($monto) {
        return 'Bs' . number_format($monto, 2, '.', ',');
    }
    
    public static function generarCodigo($prefijo = '') {
        $timestamp = time();
        $random = rand(1000, 9999);
        return $prefijo . $timestamp . $random;
    }
    
    public static function obtenerNombreUsuario() {
        return $_SESSION['nombre'] ?? 'Usuario';
    }
    
    public static function obtenerUsuarioId() {
        return $_SESSION['usuario_id'] ?? 0;
    }
    
    public static function obtenerRolUsuario() {
        return $_SESSION['rol'] ?? '';
    }
    
    public static function mostrarAlerta($tipo, $mensaje) {
        $iconos = [
            'success' => 'check-circle',
            'error' => 'exclamation-circle',
            'warning' => 'exclamation-triangle',
            'info' => 'info-circle'
        ];
        
        $icono = $iconos[$tipo] ?? 'info-circle';
        
        return '
        <div class="alert alert-' . $tipo . ' alert-dismissible fade show" role="alert">
            <i class="fas fa-' . $icono . ' me-2"></i>
            ' . htmlspecialchars($mensaje) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    }
    
    public static function redirigir($url, $mensaje = '', $tipo = 'success') {
        if ($mensaje) {
            $_SESSION['alert_message'] = $mensaje;
            $_SESSION['alert_type'] = $tipo;
        }
        header("Location: $url");
        exit();
    }
    
    public static function mostrarAlertaSesion() {
        if (isset($_SESSION['alert_message'])) {
            $mensaje = $_SESSION['alert_message'];
            $tipo = $_SESSION['alert_type'] ?? 'info';
            echo self::mostrarAlerta($tipo, $mensaje);
            unset($_SESSION['alert_message'], $_SESSION['alert_type']);
        }
    }
    
    public static function validarEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    public static function validarTelefono($telefono) {
        return preg_match('/^[0-9+\-\s]{8,20}$/', $telefono);
    }
    
    public static function obtenerEstadisticas() {
        $db = getDB();
        
        try {
            $stats = [];
            
            // Total de ventas hoy
            $stmt = $db->prepare("SELECT COUNT(*) as total, COALESCE(SUM(total), 0) as monto 
                                 FROM ventas 
                                 WHERE DATE(fecha_hora) = CURDATE() 
                                 AND anulado = FALSE");
            $stmt->execute();
            $stats['ventas_hoy'] = $stmt->fetch();
            
            // Total de productos bajos en stock
            $stmt = $db->prepare("SELECT COUNT(*) as total 
                                 FROM subpaquetes 
                                 WHERE stock <= min_stock 
                                 AND activo = TRUE");
            $stmt->execute();
            $stats['stock_bajo'] = $stmt->fetch()['total'];
            
            // Clientes con deuda
            $stmt = $db->prepare("SELECT COUNT(*) as total, COALESCE(SUM(saldo_deuda), 0) as monto 
                                 FROM clientes 
                                 WHERE saldo_deuda > 0 
                                 AND activo = TRUE");
            $stmt->execute();
            $stats['clientes_deuda'] = $stmt->fetch();
            
            // Proveedores con deuda
            $stmt = $db->prepare("SELECT COUNT(*) as total, COALESCE(SUM(saldo_deuda), 0) as monto 
                                 FROM proveedores 
                                 WHERE saldo_deuda > 0 
                                 AND activo = TRUE");
            $stmt->execute();
            $stats['proveedores_deuda'] = $stmt->fetch();
            
            // Ventas del mes
            $stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) as total 
                                 FROM ventas 
                                 WHERE MONTH(fecha_hora) = MONTH(CURDATE()) 
                                 AND YEAR(fecha_hora) = YEAR(CURDATE())
                                 AND anulado = FALSE");
            $stmt->execute();
            $stats['ventas_mes'] = $stmt->fetch()['total'];
            
            // Productos más vendidos
            $stmt = $db->prepare("SELECT sp.nombre_color, sp.codigo_color, COUNT(vd.id) as ventas
                                 FROM venta_detalles vd
                                 JOIN subpaquetes sp ON vd.subpaquete_id = sp.id
                                 JOIN ventas v ON vd.venta_id = v.id
                                 WHERE v.anulado = FALSE
                                 GROUP BY sp.id
                                 ORDER BY ventas DESC
                                 LIMIT 5");
            $stmt->execute();
            $stats['top_productos'] = $stmt->fetchAll();
            
            return $stats;
            
        } catch(PDOException $e) {
            error_log("Error obteniendo estadísticas: " . $e->getMessage());
            return [];
        }
    }
    
    public static function obtenerNotificaciones($usuario_id) {
        $db = getDB();
        
        try {
            $stmt = $db->prepare("SELECT * FROM notificaciones 
                                 WHERE usuario_id = :usuario_id 
                                 AND leida = FALSE
                                 ORDER BY fecha_hora DESC
                                 LIMIT 10");
            $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Error obteniendo notificaciones: " . $e->getMessage());
            return [];
        }
    }
    
    public static function marcarNotificacionLeida($notificacion_id) {
        $db = getDB();
        
        try {
            $stmt = $db->prepare("UPDATE notificaciones SET leida = TRUE WHERE id = :id");
            $stmt->bindParam(':id', $notificacion_id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Error marcando notificación como leída: " . $e->getMessage());
            return false;
        }
    }


}
// Inicializar funciones de sesión
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
?>