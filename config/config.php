<?php
/**
 * TESA Syllabus Monitor
 * Archivo de Configuración Principal - VERSIÓN SEGURA PARA PRODUCCIÓN
 * 
 * IMPORTANTE: No contiene credenciales hardcodeadas
 * Todas las configuraciones sensibles vienen de variables de entorno
 */

// Prevenir acceso directo
define('APP_ACCESS', true);

// ==========================================
// CARGAR VARIABLES DE ENTORNO DESDE .env
// ==========================================
// Solo para desarrollo local - Azure usa variables de entorno nativas
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignorar comentarios y líneas vacías
        if (strpos(trim($line), '#') === 0 || empty(trim($line))) {
            continue;
        }
        
        // Parsear línea KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // No sobrescribir si ya existe en el entorno
            if (!getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// ==========================================
// CONFIGURACIÓN DE ENTORNO
// ==========================================
define('DEBUG_MODE', filter_var(getenv('DEBUG_MODE') ?: 'false', FILTER_VALIDATE_BOOLEAN));

// Zona horaria
date_default_timezone_set('America/Guayaquil');

// Configuración de errores
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/error.log');
}

// ==========================================
// RUTAS DEL SISTEMA
// ==========================================
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('API_PATH', ROOT_PATH . '/api');
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('LOGS_PATH', ROOT_PATH . '/logs');

// ==========================================
// URLs BASE
// ==========================================
$base_url = getenv('BASE_URL');
if (!$base_url) {
    throw new Exception('ERROR: BASE_URL no está configurada en las variables de entorno');
}
define('BASE_URL', rtrim($base_url, '/'));
define('ASSETS_URL', BASE_URL . '/assets');

// ==========================================
// OAUTH BRIGHTSPACE - DESDE VARIABLES DE ENTORNO
// ==========================================
$oauth_client_id = getenv('OAUTH_CLIENT_ID');
$oauth_client_secret = getenv('OAUTH_CLIENT_SECRET');
$oauth_redirect_uri = getenv('OAUTH_REDIRECT_URI');

if (!$oauth_client_id || !$oauth_client_secret || !$oauth_redirect_uri) {
    throw new Exception('ERROR: Configuración OAuth incompleta. Verifica las variables de entorno');
}

define('OAUTH_CLIENT_ID', $oauth_client_id);
define('OAUTH_CLIENT_SECRET', $oauth_client_secret);
define('OAUTH_REDIRECT_URI', $oauth_redirect_uri);
define('OAUTH_AUTH_URL', getenv('OAUTH_AUTH_URL') ?: 'https://auth.brightspace.com/oauth2/auth');
define('OAUTH_TOKEN_URL', getenv('OAUTH_TOKEN_URL') ?: 'https://auth.brightspace.com/core/connect/token');
define('OAUTH_SCOPE', getenv('OAUTH_SCOPE') ?: 'content:modules:read content:toc:read core:*:* enrollment:orgunit:read grades:gradeobjects:read grades:grades:read');

// ==========================================
// API BRIGHTSPACE - DESDE VARIABLES DE ENTORNO
// ==========================================
$api_base_url = getenv('API_BASE_URL');
if (!$api_base_url) {
    throw new Exception('ERROR: API_BASE_URL no está configurada en las variables de entorno');
}

define('API_BASE_URL', rtrim($api_base_url, '/'));
define('API_VERSION', getenv('API_VERSION') ?: '1.43');
define('API_TIMEOUT', (int)(getenv('API_TIMEOUT') ?: 300));
define('API_MAX_RETRIES', (int)(getenv('API_MAX_RETRIES') ?: 3));

// Endpoints principales
define('API_PERIODOS_ENDPOINT', '/d2l/api/lp/' . API_VERSION . '/orgstructure/6606/descendants/?ouTypeId=5');
define('API_CLASES_ENDPOINT', '/d2l/api/lp/' . API_VERSION . '/orgstructure/{semesterId}/descendants/');
define('API_CONTENIDO_ENDPOINT', '/d2l/api/le/' . API_VERSION . '/{orgUnitId}/content/toc');
define('API_CALIFICACIONES_ENDPOINT', '/d2l/api/le/' . API_VERSION . '/{orgUnitId}/grades/');

// ==========================================
// CONFIGURACIÓN DE CACHE
// ==========================================
define('CACHE_ENABLED', filter_var(getenv('CACHE_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN));
define('CACHE_DURATION_HOURS', (int)(getenv('CACHE_DURATION_HOURS') ?: 6));
define('CACHE_AUTO_UPDATE', filter_var(getenv('CACHE_AUTO_UPDATE') ?: 'true', FILTER_VALIDATE_BOOLEAN));

// ==========================================
// CONFIGURACIÓN DE CARRERAS
// ==========================================
define('CARRERAS_MAP', [
    'ADM' => 'TECNOLOGÍA SUPERIOR EN ADMINISTRACIÓN',
    'MKT' => 'TECNOLOGÍA SUPERIOR EN MARKETING DIGITAL',
    'DERMA' => 'TECNOLOGÍA SUPERIOR EN DERMATOCOSMIATRÍA',
    'ADENT' => 'TECNOLOGÍA SUPERIOR EN APARATOLOGÍA DENTAL',
    'DIGMOD' => 'TECNOLOGÍA SUPERIOR EN DISEÑO Y GESTIÓN DE MODAS',
    'CIBER' => 'TECNOLOGÍA SUPERIOR EN CIBERSEGURIDAD',
    'POD' => 'TECNOLOGÍA SUPERIOR EN PODOLOGÍA',
    'DSOFT' => 'TECNOLOGÍA SUPERIOR EN DESARROLLO DE SOFTWARE Y PROGRAMACIÓN',
    'ENF' => 'ENFERMERÍA',
    'FPS' => 'TECNOLOGÍA SUPERIOR EN FINANZAS POPULARES Y SOLIDARIAS',
    'EMED' => 'EMERGENCIAS MÉDICAS',
    'CRIM' => 'CRIMINALÍSTICA',
    'GTH' => 'GESTIÓN DEL TALENTO HUMANO',
    'FISIO' => 'FISIOTERAPIA',
    'PUB' => 'PUBLICIDAD',
    'EAL' => 'ELECTIVAS ACADÉMICAS Y LIBRES',
    'ENG' => 'INGLÉS',
    'SECOM' => 'SERVICIO COMUNITARIO',
    'PRASEM' => 'PRÁCTICAS EMPRESARIALES'
]);

// ==========================================
// CONFIGURACIÓN DE SESIÓN
// ==========================================
// IMPORTANTE: Configurar ANTES de iniciar la sesión
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', !DEBUG_MODE); // HTTPS en producción
    session_start();
}

// ==========================================
// CONFIGURACIÓN DE LOGGING
// ==========================================
define('LOG_ENABLED', filter_var(getenv('LOG_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN));
define('LOG_LEVEL', getenv('LOG_LEVEL') ?: (DEBUG_MODE ? 'DEBUG' : 'ERROR'));

// ==========================================
// FUNCIONES AUXILIARES
// ==========================================

/**
 * Obtener valor de configuración desde la base de datos
 */
function getConfig($key, $default = null) {
    static $config_cache = [];
    
    if (isset($config_cache[$key])) {
        return $config_cache[$key];
    }
    
    try {
        require_once CONFIG_PATH . '/database.php';
        $db = Database::getInstance();
        
        $result = $db->fetchOne(
            "SELECT valor FROM configuracion WHERE clave = ? LIMIT 1",
            [$key]
        );
        
        $value = $result ? $result['valor'] : $default;
        $config_cache[$key] = $value;
        
        return $value;
        
    } catch (Exception $e) {
        logMessage("Error al obtener configuración '$key': " . $e->getMessage(), 'ERROR');
        return $default;
    }
}

/**
 * Actualizar valor de configuración en la base de datos
 */
function setConfig($key, $value) {
    try {
        require_once CONFIG_PATH . '/database.php';
        $db = Database::getInstance();
        
        $affected = $db->update(
            "UPDATE configuracion SET valor = ?, fecha_actualizacion = NOW() WHERE clave = ?",
            [$value, $key]
        );
        
        return $affected > 0;
        
    } catch (Exception $e) {
        logMessage("Error al actualizar configuración '$key': " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Registrar mensaje en log
 */
function logMessage($message, $level = 'INFO') {
    if (!LOG_ENABLED) return;
    
    $levels = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3];
    $current_level = $levels[LOG_LEVEL] ?? 1;
    $message_level = $levels[$level] ?? 1;
    
    if ($message_level < $current_level) return;
    
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] $message" . PHP_EOL;
    
    // Crear directorio de logs si no existe
    if (!file_exists(LOGS_PATH)) {
        @mkdir(LOGS_PATH, 0755, true);
    }
    
    $log_file = LOGS_PATH . '/app_' . date('Y-m-d') . '.log';
    @file_put_contents($log_file, $log_entry, FILE_APPEND);
    
    // En modo debug, también mostrar en pantalla
    if (DEBUG_MODE && $level === 'ERROR') {
        echo "<div style='background:#ffdddd;padding:10px;margin:10px;border:1px solid red;'>";
        echo "<strong>[$level]</strong> " . htmlspecialchars($message);
        echo "</div>";
    }
}

/**
 * Responder con JSON
 */
function jsonResponse($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Sanitizar entrada
 */
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Verificar que todas las variables de entorno requeridas estén configuradas
 */
function checkRequiredEnvVars() {
    $required = [
        'BASE_URL',
        'OAUTH_CLIENT_ID',
        'OAUTH_CLIENT_SECRET',
        'OAUTH_REDIRECT_URI',
        'API_BASE_URL',
        'DB_HOST',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_PASSWORD'
    ];
    
    $missing = [];
    foreach ($required as $var) {
        if (!getenv($var)) {
            $missing[] = $var;
        }
    }
    
    if (!empty($missing)) {
        $error = "Variables de entorno faltantes: " . implode(', ', $missing);
        logMessage($error, 'ERROR');
        
        if (DEBUG_MODE) {
            die("<h1>Error de Configuración</h1><p>$error</p>");
        } else {
            die("Error de configuración del sistema. Contacte al administrador.");
        }
    }
}

/**
 * Escapar HTML (helper simplificado)
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// ==========================================
// AUTOLOADER
// ==========================================
spl_autoload_register(function ($class) {
    $file = INCLUDES_PATH . '/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// ==========================================
// INICIALIZACIÓN
// ==========================================

// Verificar variables de entorno críticas
checkRequiredEnvVars();

// Crear directorios necesarios
$directories = [LOGS_PATH];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        @mkdir($dir, 0755, true);
    }
}

logMessage("Sistema inicializado correctamente - Entorno: " . (DEBUG_MODE ? 'DESARROLLO' : 'PRODUCCIÓN'), 'INFO');
