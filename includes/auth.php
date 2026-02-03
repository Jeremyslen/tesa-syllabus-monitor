<?php
/**
 * TESA Syllabus Monitor
 * Sistema de Autenticación y Control de Acceso
 * 
 * @package TESASyllabusMonitor
 * @author Sistema TESA
 * @version 1.0
 */

// Prevenir acceso directo
if (!defined('APP_ACCESS')) {
    die('Acceso denegado');
}

class Auth {
    
    /**
     * Verificar si el usuario está autenticado
     * 
     * @return bool
     */
    public static function isAuthenticated() {
        return isset($_SESSION['usuario_id']) && isset($_SESSION['usuario_correo']);
    }
    
    /**
     * Verificar si el usuario es administrador
     * 
     * @return bool
     */
    public static function isAdmin() {
        return self::isAuthenticated() && isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'ADMIN';
    }
    
    /**
     * Verificar si el usuario está activo
     * 
     * @return bool
     */
    public static function isActive() {
        return self::isAuthenticated() && isset($_SESSION['usuario_activo']) && $_SESSION['usuario_activo'] == 1;
    }
    
    /**
     * Requiere autenticación - redirige a login si no está autenticado
     */
    public static function requireAuth() {
        if (!self::isAuthenticated()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header('Location: ' . BASE_URL . '/login.php');
            exit;
        }
        
        // Verificar si está activo
        if (!self::isActive()) {
            self::logout();
            header('Location: ' . BASE_URL . '/login.php?error=suspended');
            exit;
        }
    }
    
    /**
     * Requiere rol de administrador
     */
    public static function requireAdmin() {
        self::requireAuth();
        
        if (!self::isAdmin()) {
            header('Location: ' . BASE_URL . '/index.php?error=access_denied');
            exit;
        }
    }
    
    /**
     * Iniciar sesión de usuario
     * 
     * @param string $correo
     * @param string $password
     * @return array ['success' => bool, 'message' => string]
     */
    public static function login($correo, $password) {
        try {
            $db = Database::getInstance();
            
            // Buscar usuario por correo
            $usuario = $db->fetchOne(
                "SELECT id, correo, password, nombre_completo, rol, activo 
                 FROM usuarios 
                 WHERE correo = ? 
                 LIMIT 1",
                [$correo]
            );
            
            // Verificar si existe el usuario
            if (!$usuario) {
                logMessage("Intento de login fallido - Usuario no existe: $correo", 'WARNING');
                return [
                    'success' => false,
                    'message' => 'Correo o contraseña incorrectos'
                ];
            }
            
            // Verificar si está activo
            if ($usuario['activo'] != 1) {
                logMessage("Intento de login de usuario suspendido: $correo", 'WARNING');
                return [
                    'success' => false,
                    'message' => 'Tu cuenta está temporalmente suspendida. Contacta al administrador.',
                    'suspended' => true
                ];
            }
            
            // Verificar contraseña
            if (!password_verify($password, $usuario['password'])) {
                logMessage("Intento de login fallido - Contraseña incorrecta: $correo", 'WARNING');
                return [
                    'success' => false,
                    'message' => 'Correo o contraseña incorrectos'
                ];
            }
            
            // Login exitoso - crear sesión
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_correo'] = $usuario['correo'];
            $_SESSION['usuario_nombre'] = $usuario['nombre_completo'];
            $_SESSION['usuario_rol'] = $usuario['rol'];
            $_SESSION['usuario_activo'] = $usuario['activo'];
            $_SESSION['login_time'] = time();
            
            // Actualizar último acceso
            $db->update(
                "UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?",
                [$usuario['id']]
            );
            
            logMessage("Login exitoso: $correo (Rol: {$usuario['rol']})", 'INFO');
            
            return [
                'success' => true,
                'message' => 'Bienvenido, ' . $usuario['nombre_completo'],
                'rol' => $usuario['rol']
            ];
            
        } catch (Exception $e) {
            logMessage("Error en login: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'message' => 'Error en el sistema. Intenta nuevamente.'
            ];
        }
    }
    
    /**
     * Cerrar sesión
     */
    public static function logout() {
        if (self::isAuthenticated()) {
            $correo = $_SESSION['usuario_correo'] ?? 'Desconocido';
            logMessage("Logout: $correo", 'INFO');
        }
        
        // Destruir todas las variables de sesión
        $_SESSION = [];
        
        // Destruir la cookie de sesión si existe
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        // Destruir la sesión
        session_destroy();
    }
    
    /**
     * Obtener datos del usuario actual
     * 
     * @return array|null
     */
    public static function getCurrentUser() {
        if (!self::isAuthenticated()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['usuario_id'] ?? null,
            'correo' => $_SESSION['usuario_correo'] ?? null,
            'nombre' => $_SESSION['usuario_nombre'] ?? null,
            'rol' => $_SESSION['usuario_rol'] ?? null,
            'activo' => $_SESSION['usuario_activo'] ?? null,
            'is_admin' => self::isAdmin()
        ];
    }
    
    /**
     * Generar contraseña aleatoria segura
     * 
     * @param int $length Longitud de la contraseña
     * @return string
     */
    public static function generatePassword($length = 12) {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $special = '#@$!';
        
        $password = '';
        
        // Asegurar al menos un carácter de cada tipo
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];
        
        // Completar el resto
        $all_chars = $uppercase . $lowercase . $numbers . $special;
        for ($i = 4; $i < $length; $i++) {
            $password .= $all_chars[random_int(0, strlen($all_chars) - 1)];
        }
        
        // Mezclar caracteres
        return str_shuffle($password);
    }
    
    /**
     * Verificar si ha pasado el tiempo de inactividad
     * 
     * @param int $minutes Minutos de inactividad permitidos
     * @return bool
     */
    public static function checkInactivity($minutes = 120) {
        if (!self::isAuthenticated()) {
            return false;
        }
        
        $login_time = $_SESSION['login_time'] ?? 0;
        $inactive_time = time() - $login_time;
        
        if ($inactive_time > ($minutes * 60)) {
            self::logout();
            return true;
        }
        
        return false;
    }
    
    /**
     * Validar formato de correo institucional
     * 
     * @param string $correo
     * @return bool
     */
    public static function isValidEmail($correo) {
        // Verificar formato de email
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        // Verificar que sea del dominio @tesa.edu.ec
        return str_ends_with(strtolower($correo), '@tesa.edu.ec');
    }
    
    /**
     * Obtener nombre de usuario desde correo
     * Ejemplo: jvera@tesa.edu.ec -> jvera
     * 
     * @param string $correo
     * @return string
     */
    public static function getUsernameFromEmail($correo) {
        return explode('@', $correo)[0] ?? '';
    }
}

/**
 * FUNCIONES AUXILIARES GLOBALES
 */

/**
 * Verificar si usuario está autenticado
 */
function isLoggedIn() {
    return Auth::isAuthenticated();
}

/**
 * Verificar si usuario es admin
 */
function isAdmin() {
    return Auth::isAdmin();
}

/**
 * Obtener usuario actual
 */
function currentUser() {
    return Auth::getCurrentUser();
}

/**
 * Requiere login
 */
function requireLogin() {
    Auth::requireAuth();
}

/**
 * Requiere admin
 */
function requireAdmin() {
    Auth::requireAdmin();
}