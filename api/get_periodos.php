<?php
/**
 * TESA Syllabus Monitor
 * API Endpoint: Obtener períodos/semestres
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../config/config.php';
require_once INCLUDES_PATH . '/api_brightspace.php';
require_once CONFIG_PATH . '/database.php';

try {
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método no permitido');
    }
    
    logMessage("Solicitando períodos...", 'INFO');
    
    // Inicializar API y Base de Datos
    $api = new ApiBrightspace();
    $db = Database::getInstance();
    
    // Obtener períodos desde el API
    $periodos_api = $api->getPeriodos();
    
    if (empty($periodos_api)) {
        jsonResponse([
            'success' => false,
            'data' => [],
            'message' => 'No se encontraron períodos'
        ], 404);
    }
    
    // Procesar y guardar períodos
    $periodos_procesados = [];
    $guardados = 0;
    $errores = [];
    
    foreach ($periodos_api as $periodo) {
        try {
            
            if (!isset($periodo['Identifier']) || !isset($periodo['Name']) || !isset($periodo['Code'])) {
                logMessage("Período inválido (faltan campos): " . json_encode($periodo), 'WARNING');
                continue;
            }
            
            $org_unit_id = intval($periodo['Identifier']);
            $nombre = trim($periodo['Name']);
            $codigo = trim($periodo['Code']);
            
            // Verificar si ya existe
            $existe = $db->fetchOne(
                "SELECT id FROM periodos WHERE org_unit_id = ? LIMIT 1",
                [$org_unit_id]
            );
            
            if ($existe) {
                // Actualizar período existente
                $db->update(
                    "UPDATE periodos 
                     SET nombre = ?, codigo = ?, fecha_actualizacion = NOW() 
                     WHERE org_unit_id = ?",
                    [$nombre, $codigo, $org_unit_id]
                );
                
                $periodo_id = $existe['id'];
                logMessage("Período actualizado: $nombre (ID: $org_unit_id)", 'DEBUG');
                
            } else {
                // Insertar nuevo período
                $periodo_id = $db->insert(
                    "INSERT INTO periodos (org_unit_id, codigo, nombre, activo, fecha_registro) 
                     VALUES (?, ?, ?, 1, NOW())",
                    [$org_unit_id, $codigo, $nombre]
                );
                
                logMessage("Período guardado: $nombre (ID: $org_unit_id)", 'DEBUG');
            }
            
            $periodos_procesados[] = [
                'id' => $periodo_id,
                'org_unit_id' => $org_unit_id,
                'codigo' => $codigo,
                'nombre' => $nombre
            ];
            
            $guardados++;
            
        } catch (Exception $e) {
            $error_msg = "Error al procesar período {$periodo['Name']}: " . $e->getMessage();
            logMessage($error_msg, 'ERROR');
            $errores[] = $error_msg;
        }
    }
    
    // Ordenar por código descendente (más reciente primero)
    usort($periodos_procesados, function($a, $b) {
        return strcmp($b['codigo'], $a['codigo']);
    });
    
    logMessage("Períodos procesados: $guardados de " . count($periodos_api), 'INFO');
    
    jsonResponse([
        'success' => true,
        'data' => $periodos_procesados,
        'message' => "Períodos obtenidos correctamente ($guardados procesados)",
        'stats' => [
            'total_api' => count($periodos_api),
            'guardados' => $guardados,
            'errores' => count($errores)
        ]
    ]);
    
} catch (Exception $e) {
    logMessage("Error en get_periodos.php: " . $e->getMessage(), 'ERROR');
    jsonResponse([
        'success' => false,
        'data' => [],
        'message' => 'Error al obtener períodos: ' . $e->getMessage()
    ], 500);
}