<?php ?>
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery (necesario para DataTables y algunos plugins) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- DataTables JS -->
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap5.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
    
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/es.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- SweetAlert2 (para alertas bonitas) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Font Awesome -->
    <script src="https://kit.fontawesome.com/your-fontawesome-kit.js" crossorigin="anonymous"></script>
    
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
    <script src="assets/js/funciones.js"></script>
    
    <script>
        // Inicializar Select2
        $(document).ready(function() {
            $('.select2').select2({
                theme: 'bootstrap-5',
                language: 'es',
                width: '100%'
            });
        });
        
        // Configurar DataTables en español
        $.extend(true, $.fn.dataTable.defaults, {
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
            },
            responsive: true,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]]
        });
        
        // Configurar SweetAlert2
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });
        
        // Función para mostrar alertas
        function mostrarAlerta(titulo, mensaje, tipo = 'success') {
            Toast.fire({
                icon: tipo,
                title: titulo,
                text: mensaje
            });
        }
        
        // Función para confirmar acciones
        function confirmarAccion(titulo, texto, callback) {
            Swal.fire({
                title: titulo,
                text: texto,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, continuar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed && typeof callback === 'function') {
                    callback();
                }
            });
        }
        
        // Auto-hide alerts after 5 seconds
        $(document).ready(function() {
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        });
        
        // Prevent form resubmission on refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        
        // Global error handler
        window.onerror = function(msg, url, lineNo, columnNo, error) {
            console.error('Error:', msg, 'URL:', url, 'Line:', lineNo, 'Column:', columnNo, 'Error object:', error);
            return false;
        };
        
        // Session timeout warning
        let sessionTimer;
        function startSessionTimer() {
            // 30 minutes = 1800000 milliseconds
            const timeout = 30 * 60 * 1000;
            const warningTime = 5 * 60 * 1000; // Warn 5 minutes before
            
            sessionTimer = setTimeout(function() {
                Swal.fire({
                    title: 'Sesión a punto de expirar',
                    text: 'Tu sesión expirará en 5 minutos. ¿Deseas extenderla?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Extender sesión',
                    cancelButtonText: 'Cerrar sesión'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Extend session
                        fetch('ajax.php?action=extender_sesion')
                            .then(() => {
                                startSessionTimer();
                                mostrarAlerta('Sesión extendida', 'Tu sesión ha sido extendida por 30 minutos más');
                            });
                    } else {
                        window.location.href = 'logout.php';
                    }
                });
            }, timeout - warningTime);
        }
        
        // Start session timer on page load
        document.addEventListener('DOMContentLoaded', function() {
            startSessionTimer();
            
            // Reset timer on user activity
            document.addEventListener('mousemove', resetSessionTimer);
            document.addEventListener('keypress', resetSessionTimer);
            document.addEventListener('click', resetSessionTimer);
            
            function resetSessionTimer() {
                clearTimeout(sessionTimer);
                startSessionTimer();
            }
        });
    </script>
</body>
</html>