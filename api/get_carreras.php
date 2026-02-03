<?php
/**
 * API Endpoint: Obtener carreras de un período desde Brightspace
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once INCLUDES_PATH . '/api_brightspace.php';
require_once INCLUDES_PATH . '/functions.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método no permitido');
    }
    
    $org_unit_id = isset($_GET['org_unit_id']) ? validarID($_GET['org_unit_id']) : null;
    
    if (!$org_unit_id) {
        respuestaJSON(false, null, 'Parámetro org_unit_id es requerido', 400);
    }
    
    logMessage("📋 Solicitando carreras del período: $org_unit_id", 'INFO');
    
    $api = new ApiBrightspace();
    
    // Obtener TODAS las clases del período
    $clases = $api->getClasesPorPeriodo($org_unit_id);
    
    if (empty($clases)) {
        logMessage("⚠️ No se encontraron clases en el período $org_unit_id", 'WARNING');
        respuestaJSON(true, [], 'No se encontraron clases en este período');
    }
    
    logMessage("✅ Total de clases obtenidas de Brightspace: " . count($clases), 'INFO');
    
    // Obtener carreras desde la base de datos
    $db = Database::getInstance();
    $carreras_bd = $db->fetchAll("SELECT codigo, nombre FROM carreras WHERE activo = 1");
    
    // Crear mapa de carreras de la BD
    $carreras_bd_map = [];
    foreach ($carreras_bd as $carrera) {
        $carreras_bd_map[$carrera['codigo']] = $carrera['nombre'];
    }
    
    logMessage("📚 Carreras activas en BD: " . count($carreras_bd_map), 'INFO');
    
    // Obtener el mapa de configuración como fallback
    $carreras_config = CARRERAS_MAP;
    
    // Extraer carreras de las clases
    $carreras_encontradas = [];
    $codigos_sin_nombre = [];
    
    foreach ($clases as $clase) {
        $nombre_clase = $clase['Name'] ?? '';
        
        // Filtrar grupos (Type Id = 4)
        if (isset($clase['Type']['Id']) && $clase['Type']['Id'] === 4) {
            continue; // Ignorar grupos
        }
        
        // Patrón para extraer código de carrera
        if (preg_match('/\d+\.S\d+\.([A-Z]+)(-|\d)/', $nombre_clase, $matches)) {
            $codigo = $matches[1];
            
            // Verificar si ya lo contabilizamos
            if (!isset($carreras_encontradas[$codigo])) {
                // Buscar nombre: 1) BD, 2) Config, 3) Código solo
                $nombre_completo = null;
                $origen = null;
                
                if (isset($carreras_bd_map[$codigo])) {
                    $nombre_completo = $carreras_bd_map[$codigo];
                    $origen = 'BD';
                } elseif (isset($carreras_config[$codigo])) {
                    $nombre_completo = $carreras_config[$codigo];
                    $origen = 'CONFIG';
                } else {
                    $nombre_completo = $codigo;
                    $origen = 'DESCONOCIDO';
                    $codigos_sin_nombre[] = $codigo;
                }
                
                $carreras_encontradas[$codigo] = [
                    'codigo' => $codigo,
                    'nombre' => $nombre_completo,
                    'total_clases' => 0,
                    'origen' => $origen
                ];
            }
            
            $carreras_encontradas[$codigo]['total_clases']++;
        }
    }
    
    // Log de códigos sin nombre
    if (!empty($codigos_sin_nombre)) {
        $codigos_unicos = array_unique($codigos_sin_nombre);
        logMessage("⚠️ Códigos SIN nombre (no están en BD ni CONFIG): " . implode(', ', $codigos_unicos), 'WARNING');
    }
    
    // Convertir a array indexado
    $carreras = array_values($carreras_encontradas);
    
    // Ordenar por nombre
    usort($carreras, function($a, $b) {
        return strcmp($a['nombre'], $b['nombre']);
    });
    
    logMessage("🎓 Total de carreras únicas encontradas: " . count($carreras), 'INFO');
    
    // Log detallado
    foreach ($carreras as $carrera) {
        $icon = $carrera['origen'] === 'BD' ? '✓' : ($carrera['origen'] === 'CONFIG' ? '~' : '✗');
        logMessage("  $icon [{$carrera['origen']}] {$carrera['codigo']}: {$carrera['nombre']} ({$carrera['total_clases']} clases)", 'DEBUG');
    }
    
    // Preparar respuesta (sin el campo 'origen' para el frontend)
    $respuesta = array_map(function($carrera) {
        return [
            'codigo' => $carrera['codigo'],
            'nombre' => $carrera['nombre'],
            'total_clases' => $carrera['total_clases']
        ];
    }, $carreras);
    
    respuestaJSON(true, $respuesta, 'Carreras obtenidas correctamente');
    
} catch (PDOException $e) {
    logMessage("❌ Error de BD en get_carreras.php: " . $e->getMessage(), 'ERROR');
    respuestaJSON(false, null, 'Error al consultar base de datos', 500);
} catch (Exception $e) {
    logMessage("❌ Error en get_carreras.php: " . $e->getMessage(), 'ERROR');
    respuestaJSON(false, null, 'Error al obtener carreras: ' . $e->getMessage(), 500);
}