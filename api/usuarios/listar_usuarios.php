<?php
/**
 * TESA Syllabus Monitor
 * API - Listar Usuarios
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
    
    // Si se proporciona un ID específico, devolver solo ese usuario
    if (isset($_GET['id']) && !empty($_GET['id'])) {
        $usuarioId = intval($_GET['id']);
        
        // 🔒 PROTECCIÓN: Si solicita el super admin (ID=1), ocultar contraseña
        if ($usuarioId === 1) {
            $usuario = $db->fetchOne(
                "SELECT id, correo, nombre_completo, rol, activo, 
                        fecha_creacion, fecha_actualizacion, ultimo_acceso,
                        '********' as password_visible
                 FROM usuarios 
                 WHERE id = ?",
                [$usuarioId]
            );
        } else {
            $usuario = $db->fetchOne(
                "SELECT id, correo, password_visible, nombre_completo, rol, activo, 
                        fecha_creacion, fecha_actualizacion, ultimo_acceso
                 FROM usuarios 
                 WHERE id = ?",
                [$usuarioId]
            );
        }
        
        if ($usuario) {
            jsonResponse([
                'success' => true,
                'data' => [$usuario]
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }
    } else {
        // Listar todos los usuarios
        $usuarios = $db->fetchAll(
            "SELECT id, correo, nombre_completo, rol, activo, 
                    fecha_creacion, fecha_actualizacion, ultimo_acceso
             FROM usuarios 
             ORDER BY fecha_creacion DESC"
        );
        
        // 🔒 Marcar el super admin para protección en frontend
        foreach ($usuarios as &$usuario) {
            $usuario['es_super_admin'] = ($usuario['id'] == 1);
        }
        unset($usuario);
        
        jsonResponse([
            'success' => true,
            'data' => $usuarios,
            'total' => count($usuarios)
        ]);
    }
    
} catch (Exception $e) {
    logMessage('Error en listar_usuarios.php: ' . $e->getMessage(), 'ERROR');
    
    jsonResponse([
        'success' => false,
        'message' => 'Error al obtener usuarios: ' . $e->getMessage()
    ], 500);
}