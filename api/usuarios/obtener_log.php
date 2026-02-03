<?php
/**
 * TESA Syllabus Monitor
 * API - Obtener Log de Acciones de Usuarios
 * 
 * @package TESASyllabusMonitor
 * @author Sistema TESA
 * @version 1.0
 */

require_once __DIR__ . '/../../config/config.php';
require_once CONFIG_PATH . '/database.php';
require_once INCLUDES_PATH . '/auth.php';

// Verificar autenticación y permisos de admin
Auth::requireAdmin();

header('Content-Type: application/json; charset=utf-8');

try {
    $db = Database::getInstance();
    
    // Obtener parámetros opcionales
    $limit = intval($_GET['limit'] ?? 50);
    $usuarioId = intval($_GET['usuario_id'] ?? 0);
    
    // Limitar máximo de registros
    if ($limit > 200) $limit = 200;
    
    // Query base
    $query = "SELECT * FROM usuarios_log";
    $params = [];
    
    // Filtrar por usuario específico si se proporciona
    if ($usuarioId > 0) {
        $query .= " WHERE usuario_afectado_id = ?";
        $params[] = $usuarioId;
    }
    
    $query .= " ORDER BY fecha_accion DESC LIMIT ?";
    $params[] = $limit;
    
    $logs = $db->fetchAll($query, $params);
    
    jsonResponse([
        'success' => true,
        'data' => $logs,
        'total' => count($logs)
    ]);
    
} catch (Exception $e) {
    logMessage('Error en obtener_log.php: ' . $e->getMessage(), 'ERROR');
    
    jsonResponse([
        'success' => false,
        'message' => 'Error al obtener el log: ' . $e->getMessage()
    ], 500);
}