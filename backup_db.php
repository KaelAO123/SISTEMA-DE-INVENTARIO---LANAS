<?php
// backup_db.php - Backup de la base de datos

require_once 'database.php';
require_once 'funciones.php';

// Verificar sesión y permisos de admin
Funciones::verificarSesion();
if (!Funciones::esAdmin()) {
    die('Acceso denegado. Solo administradores pueden hacer backup.');
}

$db = getDB();

// Procesar backup
if (isset($_POST['backup'])) {
    $nombre_backup = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $ruta_backup = 'backups/' . $nombre_backup;

    // Crear directorio si no existe
    if (!is_dir('backups')) {
        mkdir('backups', 0777, true);
    }

    try {
        // Obtener todas las tablas
        $tables = [];
        $stmt = $db->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        $sqlScript = "-- Backup de Base de Datos\n";
        $sqlScript .= "-- Sistema de Inventario Lanas\n";
        $sqlScript .= "-- Fecha: " . date('Y-m-d H:i:s') . "\n";
        $sqlScript .= "-- Generado por: " . Funciones::obtenerNombreUsuario() . "\n\n";

        // Recorrer cada tabla
        foreach ($tables as $table) {
            // Crear tabla
            $sqlScript .= "--\n-- Estructura de tabla para `$table`\n--\n";
            $stmt = $db->query("SHOW CREATE TABLE `$table`");
            $row = $stmt->fetch(PDO::FETCH_NUM);
            $sqlScript .= $row[1] . ";\n\n";

            // Datos de la tabla
            $sqlScript .= "--\n-- Volcado de datos para la tabla `$table`\n--\n";

            $stmt = $db->query("SELECT * FROM `$table`");
            $rowCount = $stmt->rowCount();

            if ($rowCount > 0) {
                $sqlScript .= "INSERT INTO `$table` VALUES\n";
                $rows = $stmt->fetchAll(PDO::FETCH_NUM);
                $values = [];

                foreach ($rows as $row) {
                    // Escapar valores
                    $escaped_values = array_map(function ($value) use ($db) {
                        if ($value === null) return 'NULL';
                        return $db->quote($value);
                    }, $row);

                    $values[] = "(" . implode(',', $escaped_values) . ")";
                }

                $sqlScript .= implode(",\n", $values) . ";\n\n";
            }
        }

        // Guardar archivo
        file_put_contents($ruta_backup, $sqlScript);

        // Comprimir
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            $zip_name = str_replace('.sql', '.zip', $ruta_backup);

            if ($zip->open($zip_name, ZipArchive::CREATE) === TRUE) {
                $zip->addFile($ruta_backup, $nombre_backup);
                $zip->close();

                // Eliminar archivo SQL original
                unlink($ruta_backup);
                $ruta_backup = $zip_name;
            }
        }

        $mensaje = "Backup creado exitosamente: " . basename($ruta_backup);
        $tipo_mensaje = 'success';
    } catch (Exception $e) {
        $mensaje = "Error al crear backup: " . $e->getMessage();
        $tipo_mensaje = 'danger';
    }
}

// Obtener backups existentes
$backups = [];
if (is_dir('backups')) {
    $files = scandir('backups', SCANDIR_SORT_DESCENDING);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $ruta = 'backups/' . $file;
            $backups[] = [
                'nombre' => $file,
                'ruta' => $ruta,
                'tamano' => filesize($ruta),
                'fecha' => date('Y-m-d H:i:s', filemtime($ruta))
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup Base de Datos - Sistema de Inventario</title>

    <?php include 'header.php'; ?>

    <style>
        .card-backup {
            transition: all 0.3s ease;
            border: none;
            border-radius: 15px;
            overflow: hidden;
        }

        .card-backup:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1) !important;
        }

        .backup-icon {
            font-size: 3rem;
            opacity: 0.8;
        }

        .backup-file {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
        }

        .backup-file:hover {
            background: #e9ecef;
        }

        .file-info {
            flex: 1;
        }

        .file-actions {
            white-space: nowrap;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-item {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.875rem;
        }

        .progress-backup {
            height: 20px;
            border-radius: 10px;
            margin: 1rem 0;
        }

        .progress-bar {
            border-radius: 10px;
        }

        .btn-restore {
            background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
            border: none;
            color: white;
        }

        .btn-restore:hover {
            background: linear-gradient(135deg, #ff5252, #ff7b7b);
            color: white;
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
                        <i class="fas fa-database me-2"></i>Backup Base de Datos
                    </h1>
                    <p class="text-muted">
                        Realice copias de seguridad y restaure datos del sistema
                    </p>
                </div>
                <div>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalBackup">
                        <i class="fas fa-plus me-2"></i>Nuevo Backup
                    </button>
                </div>
            </div>

            <!-- Mostrar mensajes -->
            <?php if (isset($mensaje)): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($mensaje); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Estadísticas -->
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-hdd"></i>
                    </div>
                    <div class="stat-value">
                        <?php echo count($backups); ?>
                    </div>
                    <div class="stat-label">Backups Existentes</div>
                </div>

                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-database"></i>
                    </div>
                    <div class="stat-value">
                        <?php
                        $tamano_total = 0;
                        foreach ($backups as $backup) {
                            $tamano_total += $backup['tamano'];
                        }
                        echo formatBytes($tamano_total);
                        ?>
                    </div>
                    <div class="stat-label">Espacio Utilizado</div>
                </div>

                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-value">
                        <?php
                        if (!empty($backups)) {
                            echo date('d/m/Y', strtotime($backups[0]['fecha']));
                        } else {
                            echo 'Nunca';
                        }
                        ?>
                    </div>
                    <div class="stat-label">Último Backup</div>
                </div>

                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="stat-value">
                        <?php echo is_dir('backups') && is_writable('backups') ? 'OK' : 'Error'; ?>
                    </div>
                    <div class="stat-label">Estado Directorio</div>
                </div>
            </div>

            <!-- Espacio en disco -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-pie me-2"></i>Espacio en Disco
                    </h5>
                </div>
                <div class="card-body">
                    <?php
                    $espacio_total = disk_total_space('.');
                    $espacio_libre = disk_free_space('.');
                    $espacio_usado = $espacio_total - $espacio_libre;
                    $porcentaje_usado = ($espacio_usado / $espacio_total) * 100;
                    ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Espacio utilizado</span>
                            <span class="fw-bold"><?php echo number_format($porcentaje_usado, 1); ?>%</span>
                        </div>
                        <div class="progress progress-backup">
                            <div class="progress-bar bg-<?php echo $porcentaje_usado > 90 ? 'danger' : ($porcentaje_usado > 70 ? 'warning' : 'success'); ?>"
                                style="width: <?php echo $porcentaje_usado; ?>%">
                            </div>
                        </div>
                    </div>
                    <div class="row text-center">
                        <div class="col-md-4">
                            <small class="text-muted d-block">Total</small>
                            <span class="fw-bold"><?php echo formatBytes($espacio_total); ?></span>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted d-block">Usado</small>
                            <span class="fw-bold"><?php echo formatBytes($espacio_usado); ?></span>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted d-block">Libre</small>
                            <span class="fw-bold"><?php echo formatBytes($espacio_libre); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lista de backups -->
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2"></i>Backups Existentes
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($backups)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No hay backups disponibles</h5>
                            <p class="text-muted">Cree su primer backup ahora</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nombre del Archivo</th>
                                        <th>Tamaño</th>
                                        <th>Fecha de Creación</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backups as $backup): ?>
                                        <tr>
                                            <td>
                                                <i class="fas fa-<?php echo pathinfo($backup['nombre'], PATHINFO_EXTENSION) == 'zip' ? 'file-archive' : 'file-code'; ?> 
                                                           me-2 text-primary"></i>
                                                <?php echo htmlspecialchars($backup['nombre']); ?>
                                            </td>
                                            <td><?php echo formatBytes($backup['tamano']); ?></td>
                                            <td><?php echo date('d/m/Y H:i:s', strtotime($backup['fecha'])); ?></td>
                                            <td class="table-actions">
                                                <button class="btn btn-sm btn-outline-success"
                                                    onclick="descargarBackup('<?php echo $backup['nombre']; ?>')"
                                                    title="Descargar">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-primary"
                                                    onclick="verBackup('<?php echo $backup['nombre']; ?>')"
                                                    title="Ver detalles">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-restore"
                                                    onclick="restaurarBackup('<?php echo $backup['nombre']; ?>')"
                                                    title="Restaurar">
                                                    <i class="fas fa-redo"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger"
                                                    onclick="eliminarBackup('<?php echo $backup['nombre']; ?>')"
                                                    title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Recomendaciones de mantenimiento -->
                        <div class="alert alert-info mt-4">
                            <h5><i class="fas fa-lightbulb me-2"></i>Recomendaciones</h5>
                            <ul class="mb-0">
                                <li>Realice backup diariamente antes de cerrar el sistema</li>
                                <li>Mantenga al menos los últimos 7 backups</li>
                                <li>Guarde copias en ubicaciones externas (USB, nube)</li>
                                <li>Verifique regularmente que los backups sean restaurables</li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Programación automática -->
            <div class="card mt-4">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-clock me-2"></i>Programación Automática
                    </h5>
                </div>
                <div class="card-body">
                    <form id="formProgramacion">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Frecuencia</label>
                                <select class="form-select" id="frecuenciaBackup">
                                    <option value="diario">Diario</option>
                                    <option value="semanal">Semanal</option>
                                    <option value="mensual">Mensual</option>
                                    <option value="manual">Manual (Desactivado)</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Hora de Ejecución</label>
                                <input type="time" class="form-control" id="horaBackup" value="23:00">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Mantener backups por</label>
                                <select class="form-select" id="retencionBackup">
                                    <option value="7">7 días</option>
                                    <option value="15">15 días</option>
                                    <option value="30" selected>30 días</option>
                                    <option value="90">90 días</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="notificarBackup">
                                    <label class="form-check-label" for="notificarBackup">
                                        Notificar por email cuando se complete el backup
                                    </label>
                                </div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="comprimirBackup" checked>
                                    <label class="form-check-label" for="comprimirBackup">
                                        Comprimir backups (recomendado)
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="text-center">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-2"></i>Guardar Configuración
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para nuevo backup -->
    <div class="modal fade" id="modalBackup" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-database me-2"></i>Crear Nuevo Backup
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nombre del Backup</label>
                            <input type="text" class="form-control"
                                value="backup_<?php echo date('Y-m-d_H-i-s'); ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descripción (opcional)</label>
                            <textarea class="form-control" rows="2"
                                placeholder="Ej: Backup antes de actualización del sistema..."></textarea>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="incluirDatos" checked>
                                <label class="form-check-label" for="incluirDatos">
                                    Incluir datos de todas las tablas
                                </label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="comprimir" checked>
                                <label class="form-check-label" for="comprimir">
                                    Comprimir archivo (recomendado)
                                </label>
                            </div>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Advertencia:</strong> El sistema puede dejar de responder durante unos segundos mientras se crea el backup.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Cancelar
                        </button>
                        <button type="submit" name="backup" class="btn btn-success">
                            <i class="fas fa-play me-2"></i>Iniciar Backup
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para restaurar backup -->
    <div class="modal fade" id="modalRestaurar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-redo me-2"></i>Restaurar Backup
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>¡ADVERTENCIA CRÍTICA!</strong>
                        <p class="mb-0 mt-2">
                            Restaurar un backup eliminará TODOS los datos actuales y los reemplazará con los datos del backup.
                            Esta acción NO se puede deshacer.
                        </p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Backup a restaurar</label>
                        <input type="text" class="form-control" id="backupRestaurarNombre" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirmar acción</label>
                        <input type="text" class="form-control"
                            placeholder="Escriba 'RESTAURAR' para confirmar"
                            id="confirmacionRestaurar">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="button" class="btn btn-danger" onclick="confirmarRestauracion()">
                        <i class="fas fa-redo me-2"></i>Restaurar Backup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        // Formatear bytes
        function formatBytes(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }

        // Descargar backup
        function descargarBackup(nombre) {
            window.open(`backups/${nombre}`, '_blank');
        }

        // Ver backup
        function verBackup(nombre) {
            // Implementar vista previa del backup
            alert(`Vista previa de ${nombre}\n\nEsta función está en desarrollo.`);
        }

        // Restaurar backup
        function restaurarBackup(nombre) {
            document.getElementById('backupRestaurarNombre').value = nombre;
            const modal = new bootstrap.Modal(document.getElementById('modalRestaurar'));
            modal.show();
        }

        function confirmarRestauracion() {
            const confirmacion = document.getElementById('confirmacionRestaurar').value;
            const nombre = document.getElementById('backupRestaurarNombre').value;

            if (confirmacion !== 'RESTAURAR') {
                alert('Debe escribir "RESTAURAR" para confirmar');
                return;
            }

            if (!confirm('¿ESTÁ ABSOLUTAMENTE SEGURO?\n\nEsta acción eliminará todos los datos actuales.')) {
                return;
            }

            // Mostrar cargando
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalRestaurar'));
            modal.hide();

            // Enviar solicitud de restauración
            fetch('ajax_backup.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=restaurar_backup&nombre=${encodeURIComponent(nombre)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Backup restaurado exitosamente. El sistema se reiniciará.');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error en la restauración');
                });
        }

        // Eliminar backup
        function eliminarBackup(nombre) {
            if (confirm(`¿Eliminar backup "${nombre}"?`)) {
                fetch('ajax_backup.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=eliminar_backup&nombre=${encodeURIComponent(nombre)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Backup eliminado');
                            location.reload();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error al eliminar backup');
                    });
            }
        }

        // Configurar programación automática
        document.getElementById('formProgramacion').addEventListener('submit', function(e) {
            e.preventDefault();

            const config = {
                frecuencia: document.getElementById('frecuenciaBackup').value,
                hora: document.getElementById('horaBackup').value,
                retencion: document.getElementById('retencionBackup').value,
                notificar: document.getElementById('notificarBackup').checked,
                comprimir: document.getElementById('comprimirBackup').checked
            };

            // Guardar en localStorage (en producción sería en servidor)
            localStorage.setItem('config_backup_auto', JSON.stringify(config));

            fetch('guardar_config_backup.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(config)
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert('Configuración guardada exitosamente');
                    } else {
                        alert('Error al guardar');
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Error de conexión');
                });

            alert('Configuración guardada exitosamente');
        });

        // Cargar configuración guardada
        document.addEventListener('DOMContentLoaded', function() {
            const config = localStorage.getItem('config_backup_auto');
            if (config) {
                const configObj = JSON.parse(config);
                document.getElementById('frecuenciaBackup').value = configObj.frecuencia;
                document.getElementById('horaBackup').value = configObj.hora;
                document.getElementById('retencionBackup').value = configObj.retencion;
                document.getElementById('notificarBackup').checked = configObj.notificar;
                document.getElementById('comprimirBackup').checked = configObj.comprimir;
            }
        });
    </script>
</body>

</html>

<?php
// Función auxiliar para formatear bytes
function formatBytes($bytes, $decimals = 2)
{
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $dm = $decimals < 0 ? 0 : $decimals;
    $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes) / log($k));
    return number_format($bytes / pow($k, $i), $dm) . ' ' . $sizes[$i];
}
?>