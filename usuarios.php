<?php
/**
 * TESA Syllabus Monitor
 * Panel de Gestión de Usuarios (Solo ADMIN)
 * 
 * @package TESASyllabusMonitor
 * @author Sistema TESA
 * @version 1.0
 */

require_once __DIR__ . '/config/config.php';
require_once CONFIG_PATH . '/database.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/functions.php';

// ✨ PROTECCIÓN: Solo administradores
Auth::requireAdmin();

// Obtener datos del usuario actual
$usuario = Auth::getCurrentUser();
?>
<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="description" content="Gestión de Usuarios - TESA Syllabus Monitor">
        <meta name="author" content="Sistema TESA">
        
        <title>Gestión de Usuarios - TESA Syllabus Monitor</title>
        
        <!-- Favicon -->
        <link rel="icon" type="image/png" href="<?php echo ASSETS_URL; ?>/img/logo-tesa.png">
        
        <!-- Estilos -->
        <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
        
        <!-- jQuery -->
        <script src="<?php echo ASSETS_URL; ?>/js/jquery.min.js"></script>
    </head>
    <body>

        <!-- ========================================
            HEADER
            ======================================== -->
        <header class="header">
            <div class="header-content">
                <h1>
                    <div class="logo">👥</div>
                    Gestión de Usuarios
                </h1>
                <div class="header-info">
                    <p>Instituto Superior Tecnológico San Antonio</p>
                    <p><small>Panel de Administración</small></p>
                </div>
            </div>
            
            <!-- Menú de Usuario -->
            <div class="user-menu">
                <div class="user-info">
                    <span class="user-icon">👨‍💼</span>
                    <div class="user-details">
                        <strong><?php echo e($usuario['nombre']); ?></strong>
                        <small><?php echo e($usuario['correo']); ?></small>
                        <span class="user-badge badge-admin">ADMIN</span>
                    </div>
                </div>
                <div class="user-actions">
                    <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-sm btn-secondary" title="Volver al Dashboard">
                        📊 Dashboard
                    </a>
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

            <!-- Contenedor de Alertas Dinámicas -->
            <div id="alerts-container"></div>

            <!-- ========================================
                BARRA DE ACCIONES
                ======================================== -->
            <section class="actions-bar">
                <div class="actions-left">
                    <h2>📋 Lista de Usuarios</h2>
                    <span id="total-usuarios" class="badge-count">0 usuarios</span>
                </div>
                <div class="actions-right">
                    <button id="btn-nuevo-usuario" class="btn btn-primary">
                        ➕ Nuevo Usuario
                    </button>
                    <button id="btn-refresh" class="btn btn-secondary" title="Recargar lista">
                        🔄 Actualizar
                    </button>
                </div>
            </section>

            <!-- ========================================
                TABLA DE USUARIOS
                ======================================== -->
            <section class="users-section">
                <div class="table-container">
                    <table id="tabla-usuarios" class="data-table">
                        <thead>
                            <tr>
                                <th width="5%">ID</th>
                                <th width="25%">Nombre Completo</th>
                                <th width="25%">Correo</th>
                                <th width="10%">Rol</th>
                                <th width="10%">Estado</th>
                                <th width="15%">Último Acceso</th>
                                <th width="10%">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Se llena dinámicamente con JavaScript -->
                        </tbody>
                    </table>
                </div>

                <!-- Estado de carga -->
                <div id="loading-table" class="loading-state" style="display: none;">
                    <div class="spinner"></div>
                    <p>Cargando usuarios...</p>
                </div>

                <!-- Estado vacío -->
                <div id="empty-state" class="empty-state" style="display: none;">
                    <div class="empty-state-icon">👤</div>
                    <h3>No hay usuarios registrados</h3>
                    <p>Crea el primer usuario haciendo clic en "Nuevo Usuario"</p>
                </div>
            </section>

            <!-- ========================================
                SECCIÓN DE LOG DE ACCIONES
                ======================================== -->
            <section class="log-section" style="margin-top: 40px;">
                <div class="section-header">
                    <h2>📜 Historial de Acciones</h2>
                    <button id="btn-toggle-log" class="btn btn-sm btn-secondary">
                        👁️ Mostrar/Ocultar
                    </button>
                </div>
                
                <div id="log-container" class="log-container" style="display: none;">
                    <div class="table-container">
                        <table id="tabla-log" class="data-table log-table">
                            <thead>
                                <tr>
                                    <th width="15%">Fecha</th>
                                    <th width="20%">Usuario Afectado</th>
                                    <th width="15%">Acción</th>
                                    <th width="20%">Realizado Por</th>
                                    <th width="30%">Detalles</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Se llena dinámicamente -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

        </main>

        <!-- ========================================
            FOOTER
            ======================================== -->
        <footer class="footer">
            <p>&copy; <?php echo date('Y'); ?> Instituto Superior Tecnológico San Antonio - TESA</p>
            <p><small>Sistema de Monitoreo de Syllabus v1.0 - Panel de Administración</small></p>
        </footer>

        <!-- ========================================
            MODAL: CREAR/EDITAR USUARIO
            ======================================== -->
        <div id="modal-usuario" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="modal-title">➕ Crear Nuevo Usuario</h3>
                    <button class="modal-close" onclick="cerrarModal()">&times;</button>
                </div>
                
                <form id="form-usuario">
                    <input type="hidden" id="usuario-id" name="id">
                    <input type="hidden" id="modal-action" value="crear">
                    
                    <div class="form-group">
                        <label for="nombre-completo">
                            👤 Nombre y Apellido*
                        </label>
                        <input 
                            type="text" 
                            id="nombre-completo" 
                            name="nombre_completo" 
                            class="form-control" 
                            placeholder="Ej: Paul Rivera"
                            required
                        >
                        <small class="form-text">El correo se generará automáticamente (primera letra del nombre + apellido)</small>
                    </div>

                    <div class="form-group">
                        <label>
                            ✉️ Correo Generado
                        </label>
                        <div id="correo-preview" class="correo-preview">
                            <span id="correo-generado">-</span>
                            <button type="button" id="btn-copiar-correo" class="btn-icon" title="Copiar correo">
                                📋
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="rol">
                            🎭 Rol *
                        </label>
                        <select id="rol" name="rol" class="form-control" required>
                            <option value="USUARIO">Usuario Normal</option>
                            <option value="ADMIN">Administrador</option>
                        </select>
                    </div>

                    <div id="password-section" style="display: none;">
                        <div class="form-group">
                            <label>
                                🔒 Contraseña Generada
                            </label>
                            <div class="password-display">
                                <input 
                                    type="text" 
                                    id="password-generada" 
                                    class="form-control" 
                                    readonly
                                >
                                <button type="button" id="btn-copiar-password" class="btn-icon" title="Copiar contraseña">
                                    📋
                                </button>
                                <button type="button" id="btn-regenerar-password" class="btn-icon" title="Generar nueva">
                                    🔄
                                </button>
                            </div>
                            <small class="form-text text-warning">
                                ⚠️ Guarda esta contraseña, envíala al usuario por WhatsApp
                            </small>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="cerrarModal()">
                            ❌ Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary" id="btn-guardar-usuario">
                            ✅ Guardar Usuario
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================
            MODAL: CAMBIAR CONTRASEÑA
            ======================================== -->
        <div id="modal-password" class="modal">
            <div class="modal-content modal-small">
                <div class="modal-header">
                    <h3>🔒 Cambiar Contraseña</h3>
                    <button class="modal-close" onclick="cerrarModalPassword()">&times;</button>
                </div>
                
                <form id="form-password">
                    <input type="hidden" id="password-usuario-id">
                    
                    <div class="form-group">
                        <label>Usuario:</label>
                        <p id="password-usuario-nombre" style="font-weight: 600; color: #2c3e50;"></p>
                    </div>

                    <div class="form-group">
                        <label for="nueva-password-input">🔒 Nueva Contraseña *</label>
                        <div class="password-display">
                            <input 
                                type="text" 
                                id="nueva-password-input" 
                                class="form-control" 
                                placeholder="Escribe la nueva contraseña"
                                minlength="8"
                                required
                            >
                            <button type="button" onclick="generarPasswordAutomatica()" class="btn-icon" title="Generar automática">
                                🎲
                            </button>
                        </div>
                        <small class="form-text">Mínimo 8 caracteres. Puedes escribir tu propia contraseña o generar una automática.</small>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="cerrarModalPassword()">
                            ❌ Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            ✅ Actualizar Contraseña
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================
            LOADING OVERLAY
            ======================================== -->
        <div class="loading-overlay">
            <div class="loading-spinner">
                <div class="spinner"></div>
                <div class="loading-text">Procesando...</div>
            </div>
        </div>

        <!-- ========================================
            JAVASCRIPT
            ======================================== -->
            <script>
            // ID del usuario actual (admin logueado)
            const USUARIO_ACTUAL_ID = <?php echo $usuario['id']; ?>;
            console.log('👤 Usuario logueado ID:', USUARIO_ACTUAL_ID);
        </script>
        <script src="<?php echo ASSETS_URL; ?>/js/usuarios.js"></script>

        <?php if (DEBUG_MODE): ?>
        <script>
            console.log('%c👥 Panel de Gestión de Usuarios', 'font-size: 18px; color: #9b59b6; font-weight: bold;');
            console.log('Admin:', '<?php echo e($usuario['nombre']); ?>');
        </script>
        <?php endif; ?>

    </body>
    </html>