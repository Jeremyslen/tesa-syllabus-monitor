<?php
/**
 * TESA Syllabus Monitor - Funciones Auxiliares
 * Funciones generales para el sistema de monitoreo de syllabus
 */

if (!defined('APP_ACCESS')) {
    die('Acceso denegado. Este archivo debe ser incluido desde un contexto válido.');
}
    
// --- EXTRACCIÓN DE DATOS DE CLASES ---

/**
 * Extraer NRC del nombre completo de la clase
 */
function extraerNRC($nombre_completo) {
    $nombre = trim($nombre_completo);
    
    // Buscar número entre paréntesis: (3410)
    if (preg_match('/\((\d{4})\)/', $nombre, $matches)) {
        return $matches[1];
    }
    
    // Buscar número de 4 dígitos antes del guión
    if (preg_match('/\.(\d{4})\s*-/', $nombre, $matches)) {
        return $matches[1];
    }
    
    // Buscar último número de 4 dígitos después de puntos
    if (preg_match('/\.(\d{4})(?:\s|$)/', $nombre, $matches)) {
        return $matches[1];
    }
    
    // Fallback: tomar el código completo antes del guión
    $partes = explode(' - ', $nombre);
    return trim($partes[0]);
}

/**
 * Extraer código de carrera desde el nombre de la clase
 */
function extraerCodigoCarrera($nombre_clase) {
    if (preg_match('/\d+\.S\d+\.([A-Z]+)-?(\d+)/', $nombre_clase, $matches)) {
        return $matches[1];
    }
    
    return null;
}

/**
 * Extraer módulo del nombre de la clase
 */
function extraerModulo($nombre_completo) {
    // Buscar letra de módulo antes del NRC (A, B, C)
    if (preg_match('/\.[A-Z]{2}\.([A-C])\.\d{4}/', $nombre_completo, $matches)) {
        return $matches[1];
    }
    
    return null;
}

// --- GESTIÓN DE CARRERAS ---

/**
 * Obtener nombre completo de la carrera desde el código
 */
function obtenerNombreCarrera($codigo) {
    $carreras = CARRERAS_MAP;
    return $carreras[$codigo] ?? $codigo;
}

/**
 * Obtener ID de carrera desde la base de datos
 */
function obtenerIdCarrera($codigo) {
    try {
        $db = Database::getInstance();
        $result = $db->fetchOne(
            "SELECT id FROM carreras WHERE codigo = ? LIMIT 1",
            [$codigo]
        );
        
        return $result ? (int)$result['id'] : null;
        
    } catch (Exception $e) {
        logMessage("Error al obtener ID de carrera '$codigo': " . $e->getMessage(), 'ERROR');
        return null;
    }
}

/**
 * Verificar si una carrera existe en la lista
 */
function esCarreraValida($codigo) {
    $carreras = CARRERAS_MAP;
    return isset($carreras[$codigo]);
}

/**
 * Obtener todas las carreras disponibles
 */
function obtenerTodasLasCarreras() {
    return CARRERAS_MAP;
}

/**
 * Filtrar clases por carrera
 */
function filtrarClasesPorCarrera($clases, $codigo_carrera) {
    if (empty($codigo_carrera)) {
        return $clases;
    }
    
    return array_filter($clases, function($clase) use ($codigo_carrera) {
        $codigo = extraerCodigoCarrera($clase['Name'] ?? '');
        return $codigo === $codigo_carrera;
    });
}

/**
 * Extraer carreras únicas de una lista de clases
 */
function extraerCarrerasDeClases($clases) {
    $carreras = [];
    
    foreach ($clases as $clase) {
        $codigo = extraerCodigoCarrera($clase['Name'] ?? '');
        
        if ($codigo && esCarreraValida($codigo) && !isset($carreras[$codigo])) {
            $carreras[$codigo] = [
                'codigo' => $codigo,
                'nombre' => obtenerNombreCarrera($codigo)
            ];
        }
    }
    
    // Ordenar por nombre
    uasort($carreras, function($a, $b) {
        return strcmp($a['nombre'], $b['nombre']);
    });
    
    return array_values($carreras);
}

// --- FORMATO Y UTILIDADES ---

/**
 * Formatear fecha para visualización
 */
function formatearFecha($fecha, $formato = 'd/m/Y H:i') {
    if (empty($fecha) || $fecha === '0000-00-00 00:00:00') {
        return '-';
    }
    
    try {
        $dt = new DateTime($fecha);
        return $dt->format($formato);
    } catch (Exception $e) {
        return $fecha;
    }
}

/**
 * Obtener tiempo transcurrido en formato legible
 */
function tiempoTranscurrido($fecha) {
    if (empty($fecha) || $fecha === '0000-00-00 00:00:00') {
        return 'Nunca';
    }
    
    try {
        $ahora = new DateTime();
        $entonces = new DateTime($fecha);
        $diferencia = $ahora->diff($entonces);
        
        if ($diferencia->y > 0) {
            return $diferencia->y . ' año' . ($diferencia->y > 1 ? 's' : '');
        } elseif ($diferencia->m > 0) {
            return $diferencia->m . ' mes' . ($diferencia->m > 1 ? 'es' : '');
        } elseif ($diferencia->d > 0) {
            return $diferencia->d . ' día' . ($diferencia->d > 1 ? 's' : '');
        } elseif ($diferencia->h > 0) {
            return $diferencia->h . ' hora' . ($diferencia->h > 1 ? 's' : '');
        } elseif ($diferencia->i > 0) {
            return $diferencia->i . ' minuto' . ($diferencia->i > 1 ? 's' : '');
        } else {
            return 'Hace un momento';
        }
    } catch (Exception $e) {
        return 'Desconocido';
    }
}

/**
 * Truncar texto con puntos suspensivos
 */
function truncarTexto($texto, $longitud = 50, $sufijo = '...') {
    if (mb_strlen($texto) <= $longitud) {
        return $texto;
    }
    
    return mb_substr($texto, 0, $longitud - mb_strlen($sufijo)) . $sufijo;
}

/**
 * Limpiar nombre de clase para mostrar
 */
function limpiarNombreClase($nombre) {
    // Eliminar el código del período al inicio
    if (preg_match('/\d+\.S\d+\.[A-Z]+\d+\.[^-]+-\s*(.+)/', $nombre, $matches)) {
        return trim($matches[1]);
    }
    
    return $nombre;
}

// --- MANEJO DE CACHE ---

/**
 * Verificar si el cache está vencido
 */
function cacheVencido($fecha_cache, $horas_duracion = null) {
    if (empty($fecha_cache)) {
        return true;
    }
    
    $horas_duracion = $horas_duracion ?? CACHE_DURATION_HOURS;
    
    try {
        $fecha_cache_dt = new DateTime($fecha_cache);
        $ahora = new DateTime();
        $diferencia_horas = ($ahora->getTimestamp() - $fecha_cache_dt->getTimestamp()) / 3600;
        
        return $diferencia_horas >= $horas_duracion;
        
    } catch (Exception $e) {
        return true;
    }
}

// --- RESPUESTAS Y VALIDACIONES ---

/**
 * Generar mensaje de respuesta JSON estandarizado
 */
function respuestaJSON($success, $data = null, $message = '', $http_code = 200) {
    http_response_code($http_code);
    header('Content-Type: application/json; charset=utf-8');
    
    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Validar ID numérico
 */
function validarID($id) {
    $id = filter_var($id, FILTER_VALIDATE_INT);
    return ($id !== false && $id > 0) ? $id : null;
}

/**
 * Verificar si una variable es un JSON válido
 */
function esJSONValido($string) {
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
}

// --- SEGURIDAD ---

/**
 * Generar token CSRF
 */
function generarTokenCSRF() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verificar token CSRF
 */
function verificarTokenCSRF($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// --- UTILIDADES DEL SISTEMA ---

/**
 * Convertir bytes a formato legible
 */
function formatearBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= (1 << (10 * $pow));
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Obtener estadísticas generales del sistema
 */
function obtenerEstadisticas() {
    try {
        $db = Database::getInstance();
        
        $stats = [
            'total_periodos' => 0,
            'total_clases' => 0,
            'clases_con_syllabus' => 0,
            'clases_sin_syllabus' => 0,
            'promedio_calificacion' => 0,
            'total_documentos' => 0,
            'ultima_actualizacion' => getConfig('ultima_sincronizacion', 'Nunca')
        ];
        
        // Total períodos activos
        $result = $db->fetchOne("SELECT COUNT(*) as total FROM periodos WHERE activo = 1");
        $stats['total_periodos'] = (int)$result['total'];
        
        // Total clases
        $result = $db->fetchOne("SELECT COUNT(*) as total FROM clases");
        $stats['total_clases'] = (int)$result['total'];
        
        // Clases con syllabus
        $result = $db->fetchOne("SELECT COUNT(*) as total FROM clases WHERE tiene_syllabus = 'SI'");
        $stats['clases_con_syllabus'] = (int)$result['total'];
        
        // Clases sin syllabus
        $result = $db->fetchOne("SELECT COUNT(*) as total FROM clases WHERE tiene_syllabus = 'NO'");
        $stats['clases_sin_syllabus'] = (int)$result['total'];
        
        // Promedio de calificación
        $result = $db->fetchOne("SELECT AVG(calificacion_final) as promedio FROM clases WHERE calificacion_final > 0");
        $stats['promedio_calificacion'] = round((float)$result['promedio'], 2);
        
        // Total documentos
        $result = $db->fetchOne("SELECT SUM(total_documentos) as total FROM clases");
        $stats['total_documentos'] = (int)$result['total'];
        
        return $stats;
        
    } catch (Exception $e) {
        logMessage("Error al obtener estadísticas: " . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * Verificar si el sistema está correctamente configurado
 */
function verificarConfiguracion() {
    $errores = [];
    
    // Verificar conexión a BD
    try {
        Database::getInstance();
    } catch (Exception $e) {
        $errores[] = "Error de conexión a base de datos: " . $e->getMessage();
    }
    
    // Verificar credenciales OAuth
    $client_id = getConfig('oauth_client_id');
    if (empty($client_id) || $client_id === 'TU_CLIENT_ID_AQUI') {
        $errores[] = "Client ID de OAuth no configurado";
    }
    
    $client_secret = getConfig('oauth_client_secret');
    if (empty($client_secret) || $client_secret === 'TU_CLIENT_SECRET_AQUI') {
        $errores[] = "Client Secret de OAuth no configurado";
    }
    
    // Verificar token
    try {
        if (!class_exists('OAuthHandler')) {
            require_once __DIR__ . '/oauth_handler.php';
        }
        $oauth = new OAuthHandler();
        if (!$oauth->hasValidToken()) {
            $errores[] = "No hay un token de acceso válido. Configure uno manualmente.";
        }
    } catch (Exception $e) {
        $errores[] = "Error al verificar token: " . $e->getMessage();
    }
    
    return [
        'configurado' => empty($errores),
        'errores' => $errores
    ];
}
