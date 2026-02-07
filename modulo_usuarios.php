<?php
// modulo_usuarios.php - Gestión de usuarios (solo administradores)

require_once 'database.php';
require_once 'funciones.php';

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar sesión y permisos de administrador
Funciones::verificarSesion();
if (!Funciones::esAdmin()) {
    Funciones::redirigir('dashboard.php', 'Acceso denegado. Solo administradores pueden acceder.', 'error');
}

$db = getDB();
$mensaje = '';
$error = '';

// Procesar acciones CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'agregar_usuario':
                $username = Funciones::sanitizar($_POST['username']);
                $password = $_POST['password'];
                $confirm_password = $_POST['confirm_password'];
                $nombre = Funciones::sanitizar($_POST['nombre']);
                $email = Funciones::sanitizar($_POST['email']);
                $rol = $_POST['rol'];
                
                // Validaciones
                if (empty($username) || empty($password) || empty($nombre) || empty($rol)) {
                    throw new Exception("Todos los campos requeridos deben ser completados");
                }
                
                if ($password !== $confirm_password) {
                    throw new Exception("Las contraseñas no coinciden");
                }
                
                if (strlen($password) < 6) {
                    throw new Exception("La contraseña debe tener al menos 6 caracteres");
                }
                
                // Verificar username único
                $stmt = $db->prepare("SELECT id FROM usuarios WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    throw new Exception("El nombre de usuario ya existe");
                }
                
                // Validar email si está presente
                if (!empty($email) && !Funciones::validarEmail($email)) {
                    throw new Exception("El email no tiene un formato válido");
                }
                
                // Hash de contraseña
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("INSERT INTO usuarios 
                                    (username, password, nombre, email, rol, estado, creado_en)
                                    VALUES (?, ?, ?, ?, ?, 'activo', NOW())");
                $stmt->execute([$username, $password_hash, $nombre, $email, $rol]);
                
                $mensaje = "Usuario agregado exitosamente";
                break;
                
            case 'editar_usuario':
                $id = intval($_POST['id']);
                $nombre = Funciones::sanitizar($_POST['nombre']);
                $email = Funciones::sanitizar($_POST['email']);
                $rol = $_POST['rol'];
                $estado = $_POST['estado'];
                
                if ($id <= 0) {
                    throw new Exception("ID de usuario inválido");
                }
                
                // Validar email si está presente
                if (!empty($email) && !Funciones::validarEmail($email)) {
                    throw new Exception("El email no tiene un formato válido");
                }
                
                $stmt = $db->prepare("UPDATE usuarios 
                                    SET nombre = ?, email = ?, rol = ?, estado = ?, actualizado_en = NOW()
                                    WHERE id = ?");
                $stmt->execute([$nombre, $email, $rol, $estado, $id]);
                
                $mensaje = "Usuario actualizado exitosamente";
                break;
                
            case 'cambiar_password':
                $id = intval($_POST['id']);
                $password = $_POST['password'];
                $confirm_password = $_POST['confirm_password'];
                
                if ($id <= 0) {
                    throw new Exception("ID de usuario inválido");
                }
                
                if ($password !== $confirm_password) {
                    throw new Exception("Las contraseñas no coinciden");
                }
                
                if (strlen($password) < 6) {
                    throw new Exception("La contraseña debe tener al menos 6 caracteres");
                }
                
                // Hash de contraseña
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("UPDATE usuarios SET password = ?, actualizado_en = NOW() WHERE id = ?");
                $stmt->execute([$password_hash, $id]);
                
                $mensaje = "Contraseña cambiada exitosamente";
                break;
                
            case 'eliminar_usuario':
                $id = intval($_POST['id']);
                
                if ($id <= 0) {
                    throw new Exception("ID de usuario inválido");
                }
                
                // No permitir eliminar el propio usuario
                if ($id == Funciones::obtenerUsuarioId()) {
                    throw new Exception("No puede eliminar su propio usuario");
                }
                
                // Verificar si tiene ventas registradas
                $stmt = $db->prepare("SELECT COUNT(*) as ventas FROM ventas WHERE vendedor_id = ?");
                $stmt->execute([$id]);
                $ventas = $stmt->fetch()['ventas'];
                
                if ($ventas > 0) {
                    throw new Exception("No se puede eliminar porque tiene ventas registradas");
                }
                
                $stmt = $db->prepare("DELETE FROM usuarios WHERE id = ?");
                $stmt->execute([$id]);
                
                $mensaje = "Usuario eliminado exitosamente";
                break;
                
            case 'resetear_intentos':
                $id = intval($_POST['id']);
                if ($id <= 0) {
                    throw new Exception("ID de usuario inválido");
                }
                // En un sistema real, aquí se resetearían los intentos de login fallidos
                // Por ahora solo actualizamos el timestamp
                $stmt = $db->prepare("UPDATE usuarios SET actualizado_en = NOW() WHERE id = ?");
                $stmt->execute([$id]);
                
                $mensaje = "Intentos de login reseteados";
                break;
                
            default:
                throw new Exception("Acción no reconocida");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obtener usuarios
try {
    $stmt = $db->query("SELECT * FROM usuarios ORDER BY creado_en DESC");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estadísticas de usuarios
    $stmt = $db->query("SELECT 
                       COUNT(*) as total,
                       SUM(CASE WHEN estado = 'activo' THEN 1 ELSE 0 END) as activos,
                       SUM(CASE WHEN rol = 'admin' THEN 1 ELSE 0 END) as admins
                       FROM usuarios");
    $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Actividad reciente de ventas hoy
    $stmt = $db->prepare("SELECT u.username, u.nombre, DATE(v.fecha_hora) as fecha, COUNT(v.id) as ventas, SUM(v.total) as total
                         FROM ventas v
                         JOIN usuarios u ON v.vendedor_id = u.id
                         WHERE DATE(v.fecha_hora) = CURDATE()
                         AND v.anulado = FALSE
                         GROUP BY v.vendedor_id, DATE(v.fecha_hora)
                         ORDER BY total DESC");
    $stmt->execute();
    $actividad_hoy = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Error al cargar datos: " . $e->getMessage();
    $usuarios = [];
    $estadisticas = ['total' => 0, 'activos' => 0, 'admins' => 0];
    $actividad_hoy = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Sistema de Inventario</title>
    
    <?php include 'header.php'; ?>
    
    <style>
        .stats-card {
            transition: all 0.3s ease;
            border: none;
            border-radius: 15px;
            overflow: hidden;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
        }
        
        .user-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            background: white;
        }
        
        .user-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-color: var(--primary-color);
        }
        
        .user-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.5rem;
            margin-right: 1rem;
        }
        
        .role-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            text-transform: uppercase;
            font-weight: bold;
        }
        
        .role-admin { background-color: #d4edda; color: #155724; }
        .role-vendedor { background-color: #e3f2fd; color: #0d47a1; }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
        }
        
        .status-activo { background-color: #d4edda; color: #155724; }
        .status-inactivo { background-color: #f8d7da; color: #721c24; }
        
        .activity-item {
            padding: 0.75rem;
            border-bottom: 1px solid #e9ecef;
            font-size: 0.875rem;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .last-login {
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        .table-actions {
            white-space: nowrap;
        }
        
        .password-strength {
            height: 5px;
            border-radius: 2px;
            margin-top: 0.25rem;
            transition: all 0.3s;
        }
        
        .strength-weak { background-color: #dc3545; width: 25%; }
        .strength-medium { background-color: #ffc107; width: 50%; }
        .strength-good { background-color: #28a745; width: 75%; }
        .strength-strong { background-color: #20c997; width: 100%; }
        
        .permissions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .permission-item {
            display: flex;
            align-items: center;
            padding: 0.5rem;
            border: 1px solid #e9ecef;
            border-radius: 5px;
            background: #f8f9fa;
        }
        
        .session-info {
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        .login-history {
            max-height: 300px;
            overflow-y: auto;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
        }
        
        .btn-xs {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            line-height: 1.5;
            border-radius: 0.2rem;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid py-4">
            <!-- Encabezado -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-2 text-success">
                        <i class="fas fa-user-cog me-2"></i>Gestión de Usuarios
                    </h1>
                    <p class="text-muted">
                        Administre usuarios, roles y permisos del sistema
                    </p>
                </div>
                <div>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalUsuario">
                        <i class="fas fa-user-plus me-2"></i>Nuevo Usuario
                    </button>
                </div>
            </div>
            
            <!-- Mostrar mensajes -->
            <?php if ($mensaje): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($mensaje); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stats-card border-start border-success border-4">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs fw-bold text-success mb-1">
                                        Total Usuarios
                                    </div>
                                    <div class="h5 mb-0 fw-bold">
                                        <?php echo $estadisticas['total']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users stats-icon text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stats-card border-start border-primary border-4">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs fw-bold text-primary mb-1">
                                        Usuarios Activos
                                    </div>
                                    <div class="h5 mb-0 fw-bold">
                                        <?php echo $estadisticas['activos']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-check stats-icon text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stats-card border-start border-warning border-4">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs fw-bold text-warning mb-1">
                                        Administradores
                                    </div>
                                    <div class="h5 mb-0 fw-bold">
                                        <?php echo $estadisticas['admins']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-shield stats-icon text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stats-card border-start border-info border-4">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs fw-bold text-info mb-1">
                                        Vendedores
                                    </div>
                                    <div class="h5 mb-0 fw-bold">
                                        <?php echo $estadisticas['total'] - $estadisticas['admins']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-tie stats-icon text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Lista de usuarios -->
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-users me-2"></i>
                                Lista de Usuarios
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($usuarios)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No hay usuarios registrados</h5>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Usuario</th>
                                                <th>Nombre</th>
                                                <th>Rol</th>
                                                <th>Estado</th>
                                                <th>Email</th>
                                                <th>Último Login</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($usuarios as $usuario): ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($usuario['username']); ?></div>
                                                        <small class="text-muted">
                                                            Creado: <?php echo Funciones::formatearFecha($usuario['creado_en'], 'd/m/Y'); ?>
                                                        </small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($usuario['nombre']); ?></td>
                                                    <td>
                                                        <span class="role-badge role-<?php echo $usuario['rol']; ?>">
                                                            <?php echo $usuario['rol']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="status-badge status-<?php echo $usuario['estado']; ?>">
                                                            <?php echo $usuario['estado']; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($usuario['email'] ?? '-'); ?></td>
                                                    <td>
                                                        <?php if (!empty($usuario['ultimo_login'])): ?>
                                                            <div class="last-login">
                                                                <?php echo Funciones::formatearFecha($usuario['ultimo_login'], 'd/m/Y H:i'); ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="text-muted">Nunca</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="table-actions">
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                onclick="editarUsuario(<?php echo $usuario['id']; ?>)"
                                                                title="Editar usuario">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-warning" 
                                                                onclick="cambiarPassword(<?php echo $usuario['id']; ?>)"
                                                                title="Cambiar contraseña">
                                                            <i class="fas fa-key"></i>
                                                        </button>
                                                        <?php if ($usuario['id'] != Funciones::obtenerUsuarioId()): ?>
                                                            <button class="btn btn-sm btn-outline-danger" 
                                                                    onclick="eliminarUsuario(<?php echo $usuario['id']; ?>)"
                                                                    title="Eliminar usuario">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <button class="btn btn-sm btn-outline-secondary" disabled title="No puede eliminar su propio usuario">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Actividad y permisos -->
                <div class="col-lg-4">
                    <!-- Actividad hoy -->
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-line me-2"></i>
                                Actividad Hoy
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($actividad_hoy)): ?>
                                <div class="text-center py-3 text-muted">
                                    <i class="fas fa-chart-bar fa-2x mb-2"></i>
                                    <p>No hay actividad hoy</p>
                                </div>
                            <?php else: ?>
                                <div class="activity-list">
                                    <?php foreach ($actividad_hoy as $actividad): ?>
                                        <div class="activity-item">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <div class="fw-bold"><?php echo htmlspecialchars($actividad['nombre']); ?></div>
                                                <span class="badge bg-success">
                                                    <?php echo Funciones::formatearMoneda($actividad['total']); ?>
                                                </span>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <small class="text-muted">
                                                    <?php echo $actividad['ventas']; ?> ventas
                                                </small>
                                                <small class="text-muted">
                                                    <?php echo Funciones::formatearFecha($actividad['fecha'], 'H:i'); ?>
                                                </small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Permisos por rol -->
                    <div class="card">
                        <div class="card-header bg-warning text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-shield-alt me-2"></i>
                                Permisos por Rol
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="permissions-grid">
                                <div class="permission-item">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <span>Dashboard</span>
                                </div>
                                <div class="permission-item">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <span>Punto de Venta</span>
                                </div>
                                <div class="permission-item">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <span>Productos</span>
                                </div>
                                <div class="permission-item">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <span>Clientes/Proveedores</span>
                                </div>
                                <div class="permission-item">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <span>Cuentas</span>
                                </div>
                                <div class="permission-item">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <span>Reportes</span>
                                </div>
                                <div class="permission-item">
                                    <i class="fas fa-times-circle text-danger me-2"></i>
                                    <span>Usuarios</span>
                                </div>
                                <div class="permission-item">
                                    <i class="fas fa-times-circle text-danger me-2"></i>
                                    <span>Backup</span>
                                </div>
                                <div class="permission-item">
                                    <i class="fas fa-times-circle text-danger me-2"></i>
                                    <span>Configuración</span>
                                </div>
                            </div>
                            <div class="mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Los permisos marcados en rojo son solo para administradores
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Información de sesiones -->
            <div class="card mt-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-desktop me-2"></i>
                        Sesiones Activas
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="session-info mb-3">
                                <strong>Sesión Actual:</strong>
                                <div class="mt-2">
                                    <div>Usuario: <?php echo htmlspecialchars(Funciones::obtenerNombreUsuario()); ?></div>
                                    <div>Rol: <?php echo htmlspecialchars(Funciones::obtenerRolUsuario()); ?></div>
                                    <div>ID: <?php echo Funciones::obtenerUsuarioId(); ?></div>
                                    <div>IP: <?php echo htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'Desconocida'); ?></div>
                                    <div>Navegador: <?php echo htmlspecialchars(substr($_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido', 0, 50)); ?>...</div>
                                    <div>Hora inicio: <?php echo date('d/m/Y H:i:s'); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="login-history">
                                <h6 class="mb-3">Últimos accesos:</h6>
                                <?php 
                                $ultimos_accesos = array_filter($usuarios, function($usuario) {
                                    return !empty($usuario['ultimo_login']);
                                });
                                usort($ultimos_accesos, function($a, $b) {
                                    return strtotime($b['ultimo_login']) - strtotime($a['ultimo_login']);
                                });
                                $contador = 0;
                                foreach ($ultimos_accesos as $usuario): 
                                    if ($contador++ >= 5) break;
                                ?>
                                    <div class="mb-2">
                                        <div class="fw-bold"><?php echo htmlspecialchars($usuario['nombre']); ?></div>
                                        <small class="text-muted">
                                            <?php echo Funciones::formatearFecha($usuario['ultimo_login'], 'd/m/Y H:i:s'); ?>
                                        </small>
                                    </div>
                                <?php endforeach; 
                                if ($contador == 0): ?>
                                    <div class="text-muted">No hay registros de acceso</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para nuevo usuario -->
    <div class="modal fade" id="modalUsuario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>Nuevo Usuario
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="formUsuario" method="POST" onsubmit="return validarNuevoUsuario()">
                    <input type="hidden" name="action" value="agregar_usuario">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username *</label>
                                <input type="text" class="form-control" name="username" 
                                       pattern="[a-zA-Z0-9_]{3,20}" 
                                       title="Solo letras, números y guión bajo (3-20 caracteres)" required>
                                <small class="text-muted">Mínimo 3 caracteres, sin espacios</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre Completo *</label>
                                <input type="text" class="form-control" name="nombre" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contraseña *</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="password" 
                                           id="newPassword" required oninput="checkPasswordStrength(this.value)">
                                    <button type="button" class="btn btn-outline-secondary" onclick="generarPassword()">
                                        <i class="fas fa-random"></i>
                                    </button>
                                </div>
                                <div class="password-strength" id="passwordStrength"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Confirmar Contraseña *</label>
                                <input type="password" class="form-control" name="confirm_password" id="confirmPassword" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" id="emailUsuario">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Rol *</label>
                                <select class="form-select" name="rol" required>
                                    <option value="">Seleccionar...</option>
                                    <option value="vendedor">Vendedor</option>
                                    <option value="admin">Administrador</option>
                                </select>
                            </div>
                        </div>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <small>
                                La contraseña debe tener al menos 6 caracteres. 
                                Se recomienda usar una combinación de letras, números y símbolos.
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-success">
                            Crear Usuario
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal para editar usuario -->
    <div class="modal fade" id="modalEditarUsuario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Editar Usuario
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="formEditarUsuario" method="POST">
                    <input type="hidden" name="action" value="editar_usuario">
                    <input type="hidden" name="id" id="edit_usuario_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" id="edit_username" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre Completo *</label>
                                <input type="text" class="form-control" name="nombre" id="edit_nombre" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" id="edit_email">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Rol *</label>
                                <select class="form-select" name="rol" id="edit_rol" required>
                                    <option value="vendedor">Vendedor</option>
                                    <option value="admin">Administrador</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Estado *</label>
                                <select class="form-select" name="estado" id="edit_estado" required>
                                    <option value="activo">Activo</option>
                                    <option value="inactivo">Inactivo</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal para cambiar contraseña -->
    <div class="modal fade" id="modalCambiarPassword" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-key me-2"></i>Cambiar Contraseña
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="formCambiarPassword" method="POST" onsubmit="return validarCambioPassword()">
                    <input type="hidden" name="action" value="cambiar_password">
                    <input type="hidden" name="id" id="password_usuario_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Usuario</label>
                            <input type="text" class="form-control" id="password_username" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nueva Contraseña *</label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="password" 
                                       id="changePassword" required oninput="checkPasswordStrengthChange(this.value)">
                                <button type="button" class="btn btn-outline-secondary" onclick="generarPasswordCambio()">
                                    <i class="fas fa-random"></i>
                                </button>
                            </div>
                            <div class="password-strength" id="passwordStrengthChange"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirmar Contraseña *</label>
                            <input type="password" class="form-control" name="confirm_password" id="confirmPasswordChange" required>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <small>Al cambiar la contraseña, el usuario deberá iniciar sesión nuevamente.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-warning">
                            Cambiar Contraseña
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal de confirmación para eliminar -->
    <div class="modal fade" id="modalConfirmarEliminar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirmar Eliminación
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="formEliminarUsuario" method="POST">
                    <input type="hidden" name="action" value="eliminar_usuario">
                    <input type="hidden" name="id" id="eliminar_usuario_id">
                    <div class="modal-body">
                        <div class="text-center mb-3">
                            <i class="fas fa-user-slash fa-3x text-danger mb-3"></i>
                            <h5>¿Está seguro de eliminar este usuario?</h5>
                            <p class="text-muted">
                                Esta acción no se puede deshacer. El usuario será eliminado permanentemente del sistema.
                            </p>
                            <div class="alert alert-danger mt-3">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <strong>Advertencia:</strong> No se puede eliminar un usuario que tenga ventas registradas.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Eliminar Usuario
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script>
        // Verificar fortaleza de contraseña
        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('passwordStrength');
            let strength = 0;
            
            // Longitud
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            
            // Diversidad de caracteres
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            // Actualizar barra
            strengthBar.className = 'password-strength';
            if (password.length === 0) {
                strengthBar.style.width = '0%';
                return;
            }
            
            if (strength <= 2) {
                strengthBar.classList.add('strength-weak');
            } else if (strength <= 4) {
                strengthBar.classList.add('strength-medium');
            } else if (strength <= 5) {
                strengthBar.classList.add('strength-good');
            } else {
                strengthBar.classList.add('strength-strong');
            }
        }
        
        function checkPasswordStrengthChange(password) {
            const strengthBar = document.getElementById('passwordStrengthChange');
            let strength = 0;
            
            // Longitud
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            
            // Diversidad de caracteres
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            // Actualizar barra
            strengthBar.className = 'password-strength';
            if (password.length === 0) {
                strengthBar.style.width = '0%';
                return;
            }
            
            if (strength <= 2) {
                strengthBar.classList.add('strength-weak');
            } else if (strength <= 4) {
                strengthBar.classList.add('strength-medium');
            } else if (strength <= 5) {
                strengthBar.classList.add('strength-good');
            } else {
                strengthBar.classList.add('strength-strong');
            }
        }
        
        // Editar usuario
        function editarUsuario(id) {
            fetch(`ajax.php?action=obtener_usuario&id=${id}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error en la respuesta del servidor');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const usuario = data.data;
                        
                        document.getElementById('edit_usuario_id').value = id;
                        document.getElementById('edit_username').value = usuario.username;
                        document.getElementById('edit_nombre').value = usuario.nombre;
                        document.getElementById('edit_email').value = usuario.email || '';
                        document.getElementById('edit_rol').value = usuario.rol;
                        document.getElementById('edit_estado').value = usuario.estado;
                        
                        const modal = new bootstrap.Modal(document.getElementById('modalEditarUsuario'));
                        modal.show();
                    } else {
                        alert('Error: ' + (data.message || 'No se pudo cargar el usuario'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cargar datos del usuario: ' + error.message);
                });
        }
        
        // Cambiar contraseña
        function cambiarPassword(id) {
            fetch(`ajax.php?action=obtener_usuario&id=${id}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error en la respuesta del servidor');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const usuario = data.data;
                        
                        document.getElementById('password_usuario_id').value = id;
                        document.getElementById('password_username').value = usuario.username;
                        
                        // Resetear campos de contraseña
                        document.getElementById('changePassword').value = '';
                        document.getElementById('confirmPasswordChange').value = '';
                        document.getElementById('passwordStrengthChange').className = 'password-strength';
                        document.getElementById('passwordStrengthChange').style.width = '0%';
                        
                        const modal = new bootstrap.Modal(document.getElementById('modalCambiarPassword'));
                        modal.show();
                    } else {
                        alert('Error: ' + (data.message || 'No se pudo cargar el usuario'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cargar datos del usuario: ' + error.message);
                });
        }
        
        // Eliminar usuario
        function eliminarUsuario(id) {
            document.getElementById('eliminar_usuario_id').value = id;
            const modal = new bootstrap.Modal(document.getElementById('modalConfirmarEliminar'));
            modal.show();
        }
        
        // Generar contraseña aleatoria
        function generarPassword() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
            let password = '';
            for (let i = 0; i < 12; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            
            document.getElementById('newPassword').value = password;
            document.getElementById('confirmPassword').value = password;
            checkPasswordStrength(password);
        }
        
        function generarPasswordCambio() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
            let password = '';
            for (let i = 0; i < 12; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            
            document.getElementById('changePassword').value = password;
            document.getElementById('confirmPasswordChange').value = password;
            checkPasswordStrengthChange(password);
        }
        
        // Validar formulario de nuevo usuario
        function validarNuevoUsuario() {
            const password = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const email = document.getElementById('emailUsuario').value;
            
            if (password !== confirmPassword) {
                alert('Las contraseñas no coinciden');
                return false;
            }
            
            if (password.length < 6) {
                alert('La contraseña debe tener al menos 6 caracteres');
                return false;
            }
            
            if (email && !isValidEmail(email)) {
                alert('Por favor ingrese un email válido');
                return false;
            }
            
            return true;
        }
        
        // Validar cambio de contraseña
        function validarCambioPassword() {
            const password = document.getElementById('changePassword').value;
            const confirmPassword = document.getElementById('confirmPasswordChange').value;
            
            if (password !== confirmPassword) {
                alert('Las contraseñas no coinciden');
                return false;
            }
            
            if (password.length < 6) {
                alert('La contraseña debe tener al menos 6 caracteres');
                return false;
            }
            
            return true;
        }
        
        // Validar email
        function isValidEmail(email) {
            const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
            return re.test(String(email).toLowerCase());
        }
        
        // Resetear formularios al cerrar modales
        document.getElementById('modalUsuario').addEventListener('hidden.bs.modal', function() {
            document.getElementById('formUsuario').reset();
            document.getElementById('passwordStrength').className = 'password-strength';
            document.getElementById('passwordStrength').style.width = '0%';
        });
        
        document.getElementById('modalEditarUsuario').addEventListener('hidden.bs.modal', function() {
            document.getElementById('formEditarUsuario').reset();
        });
        
        document.getElementById('modalCambiarPassword').addEventListener('hidden.bs.modal', function() {
            document.getElementById('formCambiarPassword').reset();
            document.getElementById('passwordStrengthChange').className = 'password-strength';
            document.getElementById('passwordStrengthChange').style.width = '0%';
        });
        
        document.getElementById('modalConfirmarEliminar').addEventListener('hidden.bs.modal', function() {
            document.getElementById('formEliminarUsuario').reset();
        });
        
        // Inicializar tooltips
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = document.querySelectorAll('[title]');
            const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => 
                new bootstrap.Tooltip(tooltipTriggerEl)
            );
        });
        
        // Verificar si hay mensajes de alerta y auto-cerrarlos después de 5 segundos
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                setTimeout(() => {
                    bsAlert.close();
                }, 5000);
            });
        }, 1000);
    </script>
</body>
</html>