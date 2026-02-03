<?php
/**
 * TESA Syllabus Monitor
 * API - Eliminar Usuario Permanentemente
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

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse([
        'success' => false,
        'message' => 'Método no permitido'
    ], 405);
}

try {
    $usuarioId = intval($_POST['id'] ?? 0);
    
    if ($usuarioId <= 0) {
        jsonResponse([
            'success' => false,
            'message' => 'ID de usuario inválido'
        ], 400);
    }

    // 🔒 PROTECCIÓN: No permitir eliminar al SUPER ADMIN (ID = 1)
    if ($usuarioId === 1) {
        jsonResponse([
            'success' => false,
            'message' => '⛔ No se puede eliminar al usuario principal del sistema'
        ], 403);
    }
    
    $db = Database::getInstance();
    $adminActual = Auth::getCurrentUser();
    
    // No permitir eliminar al propio usuario
    if ($usuarioId == $adminActual['id']) {
        jsonResponse([
            'success' => false,
            'message' => 'No puedes eliminar tu propia cuenta'
        ], 403);
    }
    
    // Obtener datos del usuario antes de eliminar
    $usuario = $db->fetchOne(
        "SELECT id, correo, nombre_completo, rol FROM usuarios WHERE id = ?",
        [$usuarioId]
    );
    
    if (!$usuario) {
        jsonResponse([
            'success' => false,
            'message' => 'Usuario no encontrado'
        ], 404);
    }
    
    // Registrar eliminación en el log ANTES de eliminar (el trigger lo hará pero por si acaso)
    $db->insert(
        "INSERT INTO usuarios_log 
         (usuario_afectado_id, usuario_afectado_correo, accion_realizada, 
          realizado_por_id, realizado_por_correo, detalles)
         VALUES (?, ?, 'ELIMINACION', ?, ?, ?)",
        [
            $usuarioId,
            $usuario['correo'],
            $adminActual['id'],
            $adminActual['correo'],
            "Usuario eliminado permanentemente. Rol: {$usuario['rol']}"
        ]
    );
    
    // Eliminar usuario (el trigger también registrará esto)
    $affected = $db->delete(
        "DELETE FROM usuarios WHERE id = ?",
        [$usuarioId]
    );
    
    if ($affected > 0) {
        logMessage("Usuario eliminado: {$usuario['correo']} por {$adminActual['correo']}", 'WARNING');
        
        jsonResponse([
            'success' => true,
            'message' => 'Usuario eliminado permanentemente'
        ]);
    } else {
        jsonResponse([
            'success' => false,
            'message' => 'No se pudo eliminar el usuario'
        ], 500);
    }
    
} catch (Exception $e) {
    logMessage('Error en eliminar_usuario.php: ' . $e->getMessage(), 'ERROR');
    
    jsonResponse([
        'success' => false,
        'message' => 'Error al eliminar usuario: ' . $e->getMessage()
    ], 500);
}