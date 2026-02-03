<?php
/**
 * TESA Syllabus Monitor
 * API Endpoint: Forzar actualización de cache
 */
set_time_limit(0);
ini_set('max_execution_time', 0);
ini_set('default_socket_timeout', 600);
ini_set('memory_limit', '512M');
ignore_user_abort(true);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once __DIR__ . '/../config/config.php';
require_once INCLUDES_PATH . '/cache_manager.php';
require_once INCLUDES_PATH . '/functions.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido. Use POST');
    }
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido');
    }
    
    $periodo_org_unit_id = isset($data['periodo_org_unit_id']) ? validarID($data['periodo_org_unit_id']) : null;
    $tipo = isset($data['tipo']) ? sanitize($data['tipo']) : 'completo';
    
    // 🆕 NUEVO PARÁMETRO: Código de carrera
    $carrera_codigo = isset($data['carrera_codigo']) ? sanitize($data['carrera_codigo']) : null;
    
    if (!$periodo_org_unit_id) {
        respuestaJSON(false, null, 'Parámetro periodo_org_unit_id es requerido', 400);
    }
    
    logMessage("🔄 Forzando actualización de cache - Período: $periodo_org_unit_id" . 
               ($carrera_codigo ? " | Carrera: $carrera_codigo" : " | COMPLETO"), 'INFO');
    
    $cache_manager = new CacheManager();
    $resultado = [];
    
    switch ($tipo) {
        case 'completo':
            // Obtener el ID interno del período
            $periodo_id = $cache_manager->obtenerIdPeriodoPorOrgUnit($periodo_org_unit_id);
            if (!$periodo_id) {
                throw new Exception("No se encontró el período con org_unit_id: $periodo_org_unit_id");
            }
            
            // ✅ NUEVO: Sincronizar con filtro de carrera
            $resultado_sync = $cache_manager->sincronizarClasesPeriodo(
                $periodo_id, 
                $periodo_org_unit_id, 
                true,  // forzar actualización
                $carrera_codigo  // 🆕 FILTRO DE CARRERA
            );
            
            $total_procesadas = $resultado_sync['nuevas'] + $resultado_sync['actualizadas'];
            $es_exitoso = $total_procesadas > 0;
            
            $resultado = [
                'total' => $resultado_sync['total'],
                'actualizadas' => $resultado_sync['actualizadas'],
                'nuevas' => $resultado_sync['nuevas'],
                'ignoradas' => $resultado_sync['ignoradas'] ?? 0,
                'errores' => $resultado_sync['errores'],
                'duracion' => $resultado_sync['duracion']
            ];
            
            if ($es_exitoso) {
                $carrera_texto = $carrera_codigo ? " de carrera $carrera_codigo" : "";
                $mensaje = sprintf(
                    '✅ Cache actualizado%s: %d clases procesadas (%d actualizadas, %d nuevas, %d ignoradas) en %.1fs',
                    $carrera_texto,
                    $total_procesadas,
                    $resultado_sync['actualizadas'],
                    $resultado_sync['nuevas'],
                    $resultado_sync['ignoradas'] ?? 0,
                    $resultado_sync['duracion']
                );
            } else {
                throw new Exception('No se pudo actualizar ninguna clase');
            }
            break;
            
        case 'clase':
            $clase_id = isset($data['clase_id']) ? validarID($data['clase_id']) : null;
            $clase_org_unit_id = isset($data['clase_org_unit_id']) ? validarID($data['clase_org_unit_id']) : null;
            
            if (!$clase_id || !$clase_org_unit_id) {
                respuestaJSON(false, null, 'Para tipo "clase" se requiere clase_id y clase_org_unit_id', 400);
            }
            
            $exito = $cache_manager->actualizarDatosClase($clase_id, $clase_org_unit_id);
            
            $resultado = [
                'exito' => $exito,
                'clase_id' => $clase_id
            ];
            
            $mensaje = $exito ? 'Clase actualizada correctamente' : 'Error al actualizar clase';
            break;
            
        case 'limpieza':
            $horas = isset($data['horas']) ? (int)$data['horas'] : 24;
            $eliminados = $cache_manager->limpiarCacheAntiguo($horas);
            
            $resultado = [
                'registros_eliminados' => $eliminados,
                'horas_antigüedad' => $horas
            ];
            
            $mensaje = "Cache antiguo limpiado. Registros eliminados: $eliminados";
            break;
            
        default:
            respuestaJSON(false, null, 'Tipo de actualización inválido. Use: completo, clase, limpieza', 400);
    }
    
    logMessage("✅ Actualización completada: $mensaje", 'INFO');
    
    respuestaJSON(true, $resultado, $mensaje);
    
} catch (Exception $e) {
    logMessage("❌ Error en actualizar_cache.php: " . $e->getMessage(), 'ERROR');
    respuestaJSON(false, null, 'Error al actualizar cache: ' . $e->getMessage(), 500);
}