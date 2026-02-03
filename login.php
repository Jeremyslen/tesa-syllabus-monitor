<?php
/**
 * TESA Syllabus Monitor
 * Página de Inicio de Sesión - Versión Moderna Card
 * 
 * @package TESASyllabusMonitor
 * @author Sistema TESA
 * @version 3.0
 */

require_once __DIR__ . '/config/config.php';
require_once CONFIG_PATH . '/database.php';
require_once INCLUDES_PATH . '/auth.php';

// Si ya está autenticado, redirigir al dashboard
if (Auth::isAuthenticated()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Variables para el formulario
$error_message = '';
$suspended_message = false;
$alert_type = '';

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($correo) || empty($password)) {
        $error_message = 'Por favor, ingrese correo y contraseña';
        $alert_type = 'warning';
    } else {
        $result = Auth::login($correo, $password);
        
        if ($result['success']) {
            // Redirigir al dashboard o a la página solicitada
            $redirect = $_SESSION['redirect_after_login'] ?? BASE_URL . '/index.php';
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $redirect);
            exit;
        } else {
            $error_message = $result['message'];
            $suspended_message = $result['suspended'] ?? false;
            $alert_type = $suspended_message ? 'warning' : 'error';
        }
    }
}

// Mensajes de error desde URL
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'suspended':
            $error_message = 'Tu cuenta está temporalmente suspendida. Contacta al administrador.';
            $suspended_message = true;
            $alert_type = 'warning';
            break;
        case 'access_denied':
            $error_message = 'No tienes permisos para acceder a esa página.';
            $alert_type = 'error';
            break;
        case 'session_expired':
            $error_message = 'Tu sesión ha expirado. Por favor, inicia sesión nuevamente.';
            $alert_type = 'info';
            break;
        case 'success':
            $error_message = 'Operación completada exitosamente';
            $alert_type = 'success';
            break;
    }
}

// Determinar icono y título según el tipo de alerta
$alert_icon = '';
$alert_title = '';

switch ($alert_type) {
    case 'success':
        $alert_icon = '✅';
        $alert_title = 'Éxito';
        break;
    case 'warning':
        $alert_icon = '⚠️';
        $alert_title = $suspended_message ? 'Cuenta Suspendida' : 'Advertencia';
        break;
    case 'error':
        $alert_icon = '❌';
        $alert_title = 'Error';
        break;
    case 'info':
        $alert_icon = 'ℹ️';
        $alert_title = 'Información';
        break;
    default:
        $alert_icon = '💡';
        $alert_title = 'Aviso';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="Login - Sistema de monitoreo de Syllabus para TESA">
    <meta name="author" content="Sistema TESA">
    
    <title>Login - TESA Syllabus Monitor</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo ASSETS_URL; ?>/img/logo-tesa.png">
    
    <!-- Estilos -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/login.css">
</head>
<body>

    <!-- ========================================
         CONTENEDOR PRINCIPAL CON FONDO
         ======================================== -->
    <div class="login-wrapper">
        
        <!-- Fondo animado con paisaje -->
        <div class="background-landscape"></div>
        
        <!-- Overlay de colores vibrantes -->
        <div class="color-overlay"></div>
        
        <!-- Partículas flotantes -->
        <div class="particles">
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
        </div>

        <!-- ========================================
             NOTIFICACIONES TOAST MODERNAS
             ======================================== -->
        <?php if ($error_message): ?>
        <div class="toast-notification toast-<?php echo $alert_type; ?>" id="main-toast">
            <div class="toast-icon">
                <?php echo $alert_icon; ?>
            </div>
            <div class="toast-content">
                <div class="toast-title"><?php echo $alert_title; ?></div>
                <div class="toast-message"><?php echo htmlspecialchars($error_message); ?></div>
            </div>
            <button class="toast-close" onclick="closeToast()">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                    <path d="M13 1L1 13M1 1L13 13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
            <div class="toast-progress"></div>
        </div>
        <?php endif; ?>

        <!-- ========================================
             TARJETA DE LOGIN (CARD)
             ======================================== -->
        <div class="login-card">
            
            <!-- Panel izquierdo con imagen de fondo de atardecer -->
            <div class="card-left">
                <!-- Overlay semitransparente para legibilidad -->
                <div class="card-left-overlay"></div>
                
                <div class="welcome-content">
                    <!-- Iconos académicos -->
                    <div class="academic-icons">
                        <span class="icon-item">💻</span>
                        <span class="icon-item">📚</span>
                        <span class="icon-item">🎓</span>
                    </div>
                    
                    <h1>Hello<br>TESA</h1>
                    <p class="welcome-text">
                        Bienvenido al sistema de<br>
                        monitoreo académico <br>
                    </p>
                    <div class="decorative-dots">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>
                
                <!-- Elementos decorativos -->
                <div class="card-decoration decoration-1"></div>
                <div class="card-decoration decoration-2"></div>
                <div class="card-decoration decoration-3"></div>
            </div>

            <!-- Panel derecho con formulario -->
            <div class="card-right">
                <div class="form-container">
                    
                    <h2>Login</h2>

                    <form method="POST" action="" class="login-form" autocomplete="off">
                        
                        <!-- Usuario -->
                        <div class="form-group">
                            <label for="correo">Usuario</label>
                            <input 
                                type="email" 
                                id="correo" 
                                name="correo" 
                                class="form-input" 
                                placeholder="usuario@tesa.edu.ec"
                                value="<?php echo htmlspecialchars($_POST['correo'] ?? ''); ?>"
                                required
                                autofocus
                            >
                        </div>

                        <!-- Password -->
                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="password-wrapper">
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password" 
                                    class="form-input" 
                                    placeholder="••••••••"
                                    required
                                >
                                <button 
                                    type="button" 
                                    class="toggle-password" 
                                    onclick="togglePassword()"
                                    title="Mostrar/Ocultar"
                                >
                                    👁️
                                </button>
                            </div>
                        </div>

                        <!-- Botón de Login -->
                        <button type="submit" class="btn-login">
                            Entrar
                        </button>

                        <!-- Link de registro -->
                        <div class="form-footer">
                            <p>SI OLVIDO SU CONTRASEÑA,CONSULTE CON EL ADMINISTRADOR</p>
                        </div>

                    </form>

                </div>

                <!-- Footer info -->
                <div class="card-footer">
                    <p>&copy; <?php echo date('Y'); ?> TESA - Instituto Superior Tecnológico San Antonio</p>
                </div>
            </div>

        </div>

    </div>

    <!-- ========================================
         JAVASCRIPT
         ======================================== -->
    <script>
        // Toggle mostrar/ocultar contraseña
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.querySelector('.toggle-password');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.textContent = '🙈';
            } else {
                passwordInput.type = 'password';
                toggleBtn.textContent = '👁️';
            }
        }

        // Sistema de notificaciones Toast
        function showToast(message, type = 'info', duration = 6000) {
            const toast = document.createElement('div');
            toast.className = `toast-notification toast-${type}`;
            toast.innerHTML = `
                <div class="toast-icon">${getToastIcon(type)}</div>
                <div class="toast-content">
                    <div class="toast-title">${getToastTitle(type)}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                        <path d="M13 1L1 13M1 1L13 13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
                <div class="toast-progress"></div>
            `;
            
            document.body.appendChild(toast);
            
            // Animación de entrada
            setTimeout(() => toast.classList.add('toast-show'), 100);
            
            // Auto-remover después del tiempo especificado
            if (duration > 0) {
                setTimeout(() => {
                    toast.classList.remove('toast-show');
                    setTimeout(() => toast.remove(), 300);
                }, duration);
            }
        }

        function getToastIcon(type) {
            const icons = {
                'success': '✅',
                'error': '❌',
                'warning': '⚠️',
                'info': 'ℹ️'
            };
            return icons[type] || '💡';
        }

        function getToastTitle(type) {
            const titles = {
                'success': 'Éxito',
                'error': 'Error',
                'warning': 'Advertencia',
                'info': 'Información'
            };
            return titles[type] || 'Aviso';
        }

        function closeToast() {
            const toast = document.getElementById('main-toast');
            if (toast) {
                toast.classList.remove('toast-show');
                setTimeout(() => toast.remove(), 300);
            }
        }

        // Auto-hide toast principal después de 6 segundos
        document.addEventListener('DOMContentLoaded', function() {
            const mainToast = document.getElementById('main-toast');
            if (mainToast) {
                setTimeout(() => {
                    closeToast();
                }, 6000);
            }

            // Enfocar el input de correo al cargar
            document.getElementById('correo').focus();
        });

        // Prevenir espacios en el correo
        document.getElementById('correo').addEventListener('input', function(e) {
            this.value = this.value.trim().toLowerCase();
        });

        // Validar formato de correo al enviar
        document.querySelector('.login-form').addEventListener('submit', function(e) {
            const correo = document.getElementById('correo').value;
            
            if (!correo.endsWith('@tesa.edu.ec')) {
                e.preventDefault();
                showToast('⚠️ Debes usar tu correo institucional @tesa.edu.ec', 'warning', 5000);
                return false;
            }
        });

        // Animar partículas
        function animateParticles() {
            const particles = document.querySelectorAll('.particle');
            particles.forEach((particle, index) => {
                const randomX = Math.random() * window.innerWidth;
                const randomY = Math.random() * window.innerHeight;
                const randomDelay = Math.random() * 5;
                
                particle.style.left = randomX + 'px';
                particle.style.top = randomY + 'px';
                particle.style.animationDelay = randomDelay + 's';
            });
        }

        animateParticles();

        // Función global para usar en otros archivos
        window.showNotification = showToast;
    </script>

    <?php if (DEBUG_MODE): ?>
    <script>
        console.log('%c🔐 TESA Syllabus Monitor - Login v3.0', 'font-size: 18px; color: #667eea; font-weight: bold;');
        console.log('%cModo DEBUG activo', 'color: #f39c12;');
    </script>
    <?php endif; ?>

</body>
</html>