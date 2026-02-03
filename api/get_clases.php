<?php
/**
 * TESA Syllabus Monitor
 * API Endpoint: Obtener clases con todos los datos
 */

// Aumentar tiempo de ejecución para procesar muchas clases
set_time_limit(600); // 10 minutos
ini_set('max_execution_time', 600);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../config/config.php';
require_once INCLUDES_PATH . '/api_brightspace.php';
require_once INCLUDES_PATH . '/cache_manager.php';
require_once INCLUDES_PATH . '/functions.php';
require_once CONFIG_PATH . '/database.php';

try {
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método no permitido');
    }
    
    // Validar parámetros
    $org_unit_id = isset($_GET['org_unit_id']) ? validarID($_GET['org_unit_id']) : null;
    $carrera_codigo = isset($_GET['carrera_codigo']) ? sanitize($_GET['carrera_codigo']) : null;
    $usar_cache = isset($_GET['usar_cache']) ? filter_var($_GET['usar_cache'], FILTER_VALIDATE_BOOLEAN) : true;
    
    if (!$org_unit_id) {
        respuestaJSON(false, null, 'Parámetro org_unit_id es requerido', 400);
    }
    
    if ($carrera_codigo && !esCarreraValida($carrera_codigo)) {
        respuestaJSON(false, null, 'Código de carrera inválido', 400);
    }
    
    logMessage("Solicitando clases - org_unit_id: $org_unit_id, Carrera: " . ($carrera_codigo ?: 'TODAS'), 'INFO');
    

    $db = Database::getInstance();
    $periodo = $db->fetchOne(
        "SELECT id, org_unit_id, nombre FROM periodos WHERE org_unit_id = ? LIMIT 1",
        [$org_unit_id]
    );
    
    $periodo_db_id = null;
    
    if (!$periodo) {
 
        logMessage("⚠️ Período con org_unit_id=$org_unit_id no existe en BD, creando...", 'WARNING');
        
        try {
            $api = new ApiBrightspace();
            $periodos_api = $api->getPeriodos();
            
           
            $periodo_data = null;
            foreach ($periodos_api as $p) {
                if (isset($p['Identifier']) && $p['Identifier'] == $org_unit_id) {
                    $periodo_data = $p;
                    break;
                }
            }
            
            if (!$periodo_data) {
                respuestaJSON(false, null, "Período con org_unit_id=$org_unit_id no encontrado en Brightspace", 404);
            }
            
          
            $cache_manager = new CacheManager();
            $periodo_db_id = $cache_manager->guardarPeriodo($periodo_data);
            
            logMessage("✅ Período creado en BD con ID=$periodo_db_id (org_unit_id=$org_unit_id)", 'INFO');
            
        } catch (Exception $e) {
            logMessage("❌ Error al crear período: " . $e->getMessage(), 'ERROR');
            respuestaJSON(false, null, 'Error al crear período: ' . $e->getMessage(), 500);
        }
    } else {
        // El período ya existe
        $periodo_db_id = intval($periodo['id']);
        logMessage("✅ Período encontrado en BD: ID=$periodo_db_id (org_unit_id=$org_unit_id)", 'INFO');
    }
 
    $cache_manager = new CacheManager();
    $clases = [];
    
    if ($usar_cache && CACHE_ENABLED) {
        try {
            $clases_cache = $cache_manager->obtenerClasesDesdeCache($periodo_db_id, $carrera_codigo);
            
            // Verificar si el cache está actualizado
            if (!empty($clases_cache)) {
                $primera_clase = $clases_cache[0];
                $cache_actualizado = !cacheVencido($primera_clase['fecha_actualizacion']);
                
                if ($cache_actualizado) {
                    logMessage("📦 Usando datos desde cache (" . count($clases_cache) . " clases)", 'INFO');
                    
                    // Formatear datos para respuesta
                    foreach ($clases_cache as $clase) {
                        $clases[] = [
                            'id' => (int)$clase['id'],
                            'nrc' => $clase['nrc'],
                            'nombre' => $clase['nombre_completo'],
                            'nombre_corto' => limpiarNombreClase($clase['nombre_completo']),
                            'tiene_syllabus' => $clase['tiene_syllabus'],
                            'calificacion_final' => (float)$clase['calificacion_final'],
                            'total_documentos' => (int)$clase['total_documentos'],
                            'carrera_codigo' => $clase['carrera_codigo'],
                            'carrera_nombre' => $clase['carrera_nombre'],
                            'ultima_actualizacion' => $clase['fecha_actualizacion'],
                            'desde_cache' => true
                        ];
                    }
                    
                    respuestaJSON(true, [
                        'clases' => $clases,
                        'sincronizacion' => [
                            'total' => count($clases),
                            'nuevas' => 0,
                            'actualizadas' => 0,
                            'errores' => 0,
                            'desde_cache' => true
                        ]
                    ], 'Clases obtenidas desde cache');
                }
            }
        } catch (Exception $e) {
            logMessage("⚠️ Error al obtener cache, consultando API: " . $e->getMessage(), 'WARNING');
        }
    }
    
    
    logMessage("🔄 Sincronizando datos desde API x1 Brightspace...", 'INFO');
    
    $resultado_sync = $cache_manager->sincronizarClasesPeriodo(
        $periodo_db_id,    
        $org_unit_id,      
        !$usar_cache      
    );
    
    
    $clases_actualizadas = $cache_manager->obtenerClasesDesdeCache($periodo_db_id, $carrera_codigo);
    
    // Formatear datos para respuesta
    foreach ($clases_actualizadas as $clase) {
        $clases[] = [
            'id' => (int)$clase['id'],
            'nrc' => $clase['nrc'],
            'nombre' => $clase['nombre_completo'],
            'nombre_corto' => limpiarNombreClase($clase['nombre_completo']),
            'tiene_syllabus' => $clase['tiene_syllabus'],
            'calificacion_final' => (float)$clase['calificacion_final'],
            'total_documentos' => (int)$clase['total_documentos'],
            'carrera_codigo' => $clase['carrera_codigo'],
            'carrera_nombre' => $clase['carrera_nombre'],
            'ultima_actualizacion' => $clase['fecha_actualizacion'],
            'desde_cache' => false
        ];
    }
    
    $mensaje = sprintf(
        '✅ Clases sincronizadas. Total: %d, Nuevas: %d, Actualizadas: %d, Errores: %d',
        $resultado_sync['total'],
        $resultado_sync['nuevas'],
        $resultado_sync['actualizadas'],
        $resultado_sync['errores']
    );
    
    logMessage($mensaje, 'INFO');
    
    respuestaJSON(true, [
        'clases' => $clases,
        'sincronizacion' => $resultado_sync
    ], $mensaje);
    
} catch (Exception $e) {
    logMessage("❌ Error en get_clases.php: " . $e->getMessage(), 'ERROR');
    respuestaJSON(false, null, 'Error al obtener clases: ' . $e->getMessage(), 500);
}