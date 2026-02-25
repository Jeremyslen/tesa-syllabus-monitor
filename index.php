<?php
/**
 * TESA Syllabus Monitor
 * Página Principal - Dashboard
 * 
 * @package TESASyllabusMonitor
 * @author Sistema TESA
 * @version 1.0
 */

require_once __DIR__ . '/config/config.php';
require_once CONFIG_PATH . '/database.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/oauth_handler.php';


Auth::requireAuth();


$usuario = Auth::getCurrentUser();


$config_check = verificarConfiguracion();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="Sistema de monitoreo de Syllabus para TESA - Instituto Superior Tecnológico San Antonio">
    <meta name="author" content="Sistema TESA">
    
    <title>TESA Syllabus Monitor - Dashboard</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/img/logo-tesa.png">
    
    <!-- Estilos -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- jQuery -->
    <script src="assets/js/jquery.min.js"></script>
</head>
<body>

    <!-- ========================================
         HEADER
         ======================================== -->
    <header class="header">
        <div class="header-content">
            <h1>
                <div class="logo">📚</div>
                TESA Syllabus Monitor
            </h1>
            <div class="header-info">
                <p>Instituto Superior Tecnológico San Antonio</p>
                <p><small>Sistema de Monitoreo Académico</small></p>
            </div>
        </div>
        
        
        <div class="user-menu">
            <div class="user-info">
                <span class="user-icon"><?php echo $usuario['is_admin'] ? '👨‍💼' : '👤'; ?></span>
                <div class="user-details">
                    <strong><?php echo e($usuario['nombre']); ?></strong>
                    <small><?php echo e($usuario['correo']); ?></small>
                    <span class="user-badge badge-<?php echo strtolower($usuario['rol']); ?>">
                        <?php echo $usuario['rol']; ?>
                    </span>
                </div>
            </div>
            <div class="user-actions">
                <?php if ($usuario['is_admin']): ?>
                <a href="<?php echo BASE_URL; ?>/usuarios.php" class="btn btn-sm btn-secondary" title="Gestionar Usuarios">
                    👥 Usuarios
                </a>
                <?php endif; ?>
                <a href="<?php echo BASE_URL; ?>/logout.php" class="btn btn-sm btn-danger" title="Cerrar Sesión">
                    🚪 Salir
                </a>
            </div>
        </div>
    </header>

    <!-- ========================================
         CONTENIDO PRINCIPAL
         ======================================== -->
    <main class="container">
        
        <!-- Alertas de Configuración -->
        <?php if (!$config_check['configurado']): ?>
        <div class="alert alert-danger">
            <strong>⚠️ Configuración Incompleta:</strong>
            <ul style="margin: 10px 0 0 20px;">
                <?php foreach ($config_check['errores'] as $error): ?>
                    <li><?php echo e($error); ?></li>
                <?php endforeach; ?>
            </ul>
            <p style="margin-top: 10px;">
                <strong>Solución:</strong> Usa el endpoint <code>api/verificar_token.php</code> 
                para configurar el token OAuth manualmente desde Postman.
            </p>
        </div>
        <?php endif; ?>

        <!-- Contenedor de Alertas Dinámicas -->
        <div class="alerts-container"></div>

        <!-- ========================================
             SECCIÓN DE FILTROS
             ======================================== -->
        <section class="filters-section">
            <div class="filters-row">
                <!-- Selector de Período -->
                <div class="form-group">
                    <label for="periodo">
                        📅 Período / Semestre
                    </label>
                    <select id="periodo" class="form-control" disabled>
                        <option value="">Cargando períodos...</option>
                    </select>
                </div>

                <!-- Selector de Carrera -->
                <div class="form-group">
                    <label for="carrera">
                        🎓 Carrera
                    </label>
                    <select id="carrera" class="form-control" disabled>
                        <option value="">Primero seleccione un período</option>
                    </select>
                </div>

                <!-- Selector de Módulo -->
                <div class="form-group">
                    <label for="modulo">
                        📚 Módulo
                    </label>
                    <select id="modulo" class="form-control" disabled>
                        <option value="">Todos los módulos</option>
                        <option value="A">Módulo A</option>
                        <option value="B">Módulo B</option>
                    </select>
                </div>

                <!-- Botón Actualizar (Solo Carrera) -->
                <div class="form-group">
                    <button id="btn-actualizar" class="btn btn-success" title="Actualizar solo las clases de la carrera seleccionada (2-5 min)">
                        🔄 Actualizar Carrera
                    </button>
                    <small class="form-text">Actualiza solo la carrera seleccionada</small>
                </div>
            </div>
        </section>

        <!-- ========================================
             SECCIÓN DE RESULTADOS (TABLA)
             ======================================== -->
        <section class="results-section" style="display: none;">
            <div class="results-header">
                <h2>📊 Resultados</h2>
                <span class="results-count">0 clases</span>
            </div>
            
            <div class="table-container">
                <table id="tabla-clases" class="data-table">
                    <thead>
                        <tr>
                            <th>NRC</th>
                            <th>Nombre de la Clase</th>
                            <th>Bienvenida</th>
                            <th>Syllabus</th>
                            <th>Calificación Final</th>
                            <th>Documentos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Se llena dinámicamente con JavaScript -->
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ========================================
             ESTADO VACÍO
             ======================================== -->
        <section class="empty-state-container">
            <div class="empty-state">
                <div class="empty-state-icon">📋</div>
                <h3>Seleccione los filtros</h3>
                <p>Seleccione un período y una carrera para ver las clases</p>
            </div>
        </section>

    </main>

    <!-- ========================================
         FOOTER
         ======================================== -->
    <footer class="footer">
        <p>&copy; <?php echo date('Y'); ?> Instituto Superior Tecnológico San Antonio - TESA</p>
        <p><small>Sistema de Monitoreo de Syllabus v1.0</small></p>
    </footer>

    <!-- ========================================
         LOADING OVERLAY
         ======================================== -->
    <div class="loading-overlay">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <div class="loading-text">Cargando...</div>
        </div>
    </div>

    <!-- ========================================
         JAVASCRIPT
         ======================================== -->
    <script src="assets/js/main.js"></script>

    <!-- Debug Info (solo en modo desarrollo) -->
    <?php if (DEBUG_MODE): ?>
    <script>
        console.log('%c🚀 TESA Syllabus Monitor', 'font-size: 20px; color: #3498db; font-weight: bold;');
        console.log('%cModo DEBUG activo', 'color: #f39c12;');
        console.log('Usuario autenticado:', {
            nombre: '<?php echo e($usuario['nombre']); ?>',
            correo: '<?php echo e($usuario['correo']); ?>',
            rol: '<?php echo e($usuario['rol']); ?>',
            isAdmin: <?php echo $usuario['is_admin'] ? 'true' : 'false'; ?>
        });
        console.log('Configuración:', {
            apiBaseUrl: '<?php echo API_BASE_URL; ?>',
            cacheEnabled: <?php echo CACHE_ENABLED ? 'true' : 'false'; ?>,
            cacheDuration: '<?php echo CACHE_DURATION_HOURS; ?> horas',
            debugMode: true
        });
    </script>
    <?php endif; ?>

</body>
</html>