<?php ?>
<nav class="sidebar bg-white shadow-sm">
    <div class="sidebar-content">
        <div class="sidebar-brand text-center py-4 border-bottom">
            <div class="mb-3">
                <i class="fas fa-warehouse fa-2x text-success"></i>
            </div>
            <h5 class="mb-1 text-success">Inventario Lanas</h5>
        </div>
    
        <div class="sidebar-menu px-3 py-4">
            <ul class="nav flex-column">
                <li class="nav-item mb-2">
                    <a class="nav-link d-flex align-items-center <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" 
                       href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-3"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <li class="nav-item mb-2">
                    <a class="nav-link d-flex align-items-center <?php echo basename($_SERVER['PHP_SELF']) == 'modulo_ventas.php' ? 'active' : ''; ?>" 
                       href="modulo_ventas.php">
                        <i class="fas fa-cash-register me-3"></i>
                        <span>Punto de Venta</span>
                    </a>
                </li>
                
                <li class="nav-item mb-2">
                    <a class="nav-link d-flex align-items-center <?php echo basename($_SERVER['PHP_SELF']) == 'modulo_productos.php' ? 'active' : ''; ?>" 
                       href="modulo_productos.php">
                        <i class="fas fa-boxes me-3"></i>
                        <span>Productos</span>
                    </a>
                </li>
                
                <li class="nav-item mb-2">
                    <a class="nav-link d-flex align-items-center <?php echo basename($_SERVER['PHP_SELF']) == 'modulo_clientes.php' ? 'active' : ''; ?>" 
                       href="modulo_clientes.php">
                        <i class="fas fa-users me-3"></i>
                        <span>Clientes/Proveedores</span>
                    </a>
                </li>
                
                <li class="nav-item mb-2">
                    <a class="nav-link d-flex align-items-center <?php echo basename($_SERVER['PHP_SELF']) == 'modulo_cuentas.php' ? 'active' : ''; ?>" 
                       href="modulo_cuentas.php">
                        <i class="fas fa-hand-holding-usd me-3"></i>
                        <span>Cuentas</span>
                    </a>
                </li>
                
                <li class="nav-item mb-2">
                    <a class="nav-link d-flex align-items-center <?php echo basename($_SERVER['PHP_SELF']) == 'modulo_reportes.php' ? 'active' : ''; ?>" 
                       href="modulo_reportes.php">
                        <i class="fas fa-chart-pie me-3"></i>
                        <span>Reportes</span>
                    </a>
                </li>
                
                <?php if (Funciones::esAdmin()): ?>
                <li class="nav-item mb-2">
                    <a class="nav-link d-flex align-items-center <?php echo basename($_SERVER['PHP_SELF']) == 'modulo_usuarios.php' ? 'active' : ''; ?>" 
                       href="modulo_usuarios.php">
                        <i class="fas fa-user-cog me-3"></i>
                        <span>Usuarios</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <li class="nav-item mb-2">
                    <a class="nav-link d-flex align-items-center" href="backup_db.php">
                        <i class="fas fa-database me-3"></i>
                        <span>Backup</span>
                    </a>
                </li>
                
                <li class="nav-item mt-4 pt-3 border-top">
                    <a class="nav-link d-flex align-items-center text-danger" href="logout.php">
                        <i class="fas fa-sign-out-alt me-3"></i>
                        <span>Cerrar Sesión</span>
                    </a>
                </li>
            </ul>
        </div>
        
    </div>
            <div class="px-3 py-3 border-bottom">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0">
                    <div class="rounded-circle bg-success d-flex align-items-center justify-content-center" 
                         style="width: 40px; height: 40px;">
                        <i class="fas fa-user text-white"></i>
                    </div>
                </div>
                <div class="flex-grow-1 ms-3">
                    <h6 class="mb-0 text-success"><?php echo Funciones::obtenerNombreUsuario(); ?></h6>
                    <small class="text-muted">
                        <i class="fas fa-user-tag"></i> 
                        <?php echo Funciones::obtenerRolUsuario() == 'admin' ? 'Administrador' : 'Vendedor'; ?>
                    </small>
                </div>
            </div>
        </div>
</nav>

<script>
    // Actualizar fecha y hora
    function updateDateTime() {
        const now = new Date();
        const options = { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        };
        
        document.getElementById('currentDateTime').textContent = now.toLocaleDateString('es-ES',options);
    }
    
    // Actualizar cada segundo
    // setInterval(updateDateTime, 1000);
    // updateDateTime();
    
    // Toggle sidebar en móviles
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        
        if (sidebar.style.left === '0px') {
            sidebar.style.left = '-250px';
            mainContent.style.marginLeft = '0';
        } else {
            sidebar.style.left = '0';
            mainContent.style.marginLeft = '250px';
        }
    });
    
    // Ajustar sidebar en resize
    window.addEventListener('resize', function() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        
        if (window.innerWidth <= 992) {
            sidebar.style.left = '-250px';
            mainContent.style.marginLeft = '0';
        } else {
            sidebar.style.left = '0';
            mainContent.style.marginLeft = '250px';
        }
    });
    
    // Inicializar en móviles
    if (window.innerWidth <= 992) {
        document.querySelector('.sidebar').style.left = '-250px';
        document.querySelector('.main-content').style.marginLeft = '0';
    }
</script>
<?php

?>