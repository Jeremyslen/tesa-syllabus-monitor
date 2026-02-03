<?php
/**
 * TESA Syllabus Monitor
 * API - Actualizar Usuario (Activar/Suspender/Cambiar Password)
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
    $accion = trim($_POST['accion'] ?? '');
    
    if ($usuarioId <= 0) {
        jsonResponse([
            'success' => false,
            'message' => 'ID de usuario inválido'
        ], 400);
    }
    // 🔒 PROTECCIÓN: No permitir modificar al SUPER ADMIN (ID = 1)
    if ($usuarioId === 1) {
        jsonResponse([
            'success' => false,
            'message' => '⛔ No se puede modificar al usuario principal del sistema'
        ], 403);
    }
    
    $db = Database::getInstance();
    
    // Obtener datos del usuario
    $usuario = $db->fetchOne(
        "SELECT id, correo, nombre_completo, activo FROM usuarios WHERE id = ?",
        [$usuarioId]
    );
    
    if (!$usuario) {
        jsonResponse([
            'success' => false,
            'message' => 'Usuario no encontrado'
        ], 404);
    }
    
    $adminActual = Auth::getCurrentUser();
    
    // Ejecutar acción según el tipo
    switch ($accion) {
        case 'activar':
            $db->update(
                "UPDATE usuarios SET activo = 1 WHERE id = ?",
                [$usuarioId]
            );
            
            $db->insert(
                "INSERT INTO usuarios_log 
                 (usuario_afectado_id, usuario_afectado_correo, accion_realizada, 
                  realizado_por_id, realizado_por_correo, detalles)
                 VALUES (?, ?, 'ACTIVACION', ?, ?, 'Usuario reactivado')",
                [$usuarioId, $usuario['correo'], $adminActual['id'], $adminActual['correo']]
            );
            
            logMessage("Usuario activado: {$usuario['correo']} por {$adminActual['correo']}", 'INFO');
            
            jsonResponse([
                'success' => true,
                'message' => 'Usuario activado correctamente'
            ]);
            break;
            
        case 'suspender':
            // No permitir suspender al propio usuario
            if ($usuarioId == $adminActual['id']) {
                jsonResponse([
                    'success' => false,
                    'message' => 'No puedes suspender tu propia cuenta'
                ], 403);
            }
            
            $db->update(
                "UPDATE usuarios SET activo = 0 WHERE id = ?",
                [$usuarioId]
            );
            
            $db->insert(
                "INSERT INTO usuarios_log 
                 (usuario_afectado_id, usuario_afectado_correo, accion_realizada, 
                  realizado_por_id, realizado_por_correo, detalles)
                 VALUES (?, ?, 'DESACTIVACION', ?, ?, 'Usuario suspendido temporalmente')",
                [$usuarioId, $usuario['correo'], $adminActual['id'], $adminActual['correo']]
            );
            
            logMessage("Usuario suspendido: {$usuario['correo']} por {$adminActual['correo']}", 'WARNING');
            
            jsonResponse([
                'success' => true,
                'message' => 'Usuario suspendido correctamente'
            ]);
            break;
            
        case 'cambiar_password':
            $nuevaPassword = trim($_POST['nueva_password'] ?? '');
            
            if (empty($nuevaPassword)) {
                jsonResponse([
                    'success' => false,
                    'message' => 'La contraseña no puede estar vacía'
                ], 400);
            }
            
            if (strlen($nuevaPassword) < 8) {
                jsonResponse([
                    'success' => false,
                    'message' => 'La contraseña debe tener al menos 8 caracteres'
                ], 400);
            }
            
            // Hash de la contraseña
            $passwordHash = password_hash($nuevaPassword, PASSWORD_DEFAULT);
            
            // Actualizar AMBOS campos: password y password_visible
            $updated = $db->update(
                "UPDATE usuarios SET password = ?, password_visible = ? WHERE id = ?",
                [$passwordHash, $nuevaPassword, $usuarioId]
            );
            
            if ($updated === false) {
                jsonResponse([
                    'success' => false,
                    'message' => 'Error al actualizar en la base de datos'
                ], 500);
            }
            
            // Log
            $db->insert(
                "INSERT INTO usuarios_log 
                (usuario_afectado_id, usuario_afectado_correo, accion_realizada, 
                realizado_por_id, realizado_por_correo, detalles)
                VALUES (?, ?, 'CAMBIO_PASSWORD', ?, ?, 'Contraseña actualizada por administrador')",
                [$usuarioId, $usuario['correo'], $adminActual['id'], $adminActual['correo']]
            );
            
            logMessage("Contraseña cambiada: {$usuario['correo']} por {$adminActual['correo']} - Nueva: $nuevaPassword", 'INFO');
            
            jsonResponse([
                'success' => true,
                'message' => 'Contraseña actualizada correctamente'
            ]);
            break;
            
        default:
            jsonResponse([
                'success' => false,
                'message' => 'Acción no válida'
            ], 400);
    }
    
} catch (Exception $e) {
    logMessage('Error en actualizar_usuario.php: ' . $e->getMessage(), 'ERROR');
    
    jsonResponse([
        'success' => false,
        'message' => 'Error al actualizar usuario: ' . $e->getMessage()
    ], 500);
}