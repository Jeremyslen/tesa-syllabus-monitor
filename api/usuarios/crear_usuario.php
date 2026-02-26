<?php
/**
 * TESA Syllabus Monitor
 * API - Crear Usuario
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
    // Validar datos recibidos
    $nombreCompleto = trim($_POST['nombre_completo'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $rol = trim($_POST['rol'] ?? 'USUARIO');
    
    // Validaciones
    if (empty($nombreCompleto)) {
        jsonResponse([
            'success' => false,
            'message' => 'El nombre completo es requerido'
        ], 400);
    }
    
    if (empty($correo) || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        jsonResponse([
            'success' => false,
            'message' => 'El correo es inválido'
        ], 400);
    }
    
    
    if (empty($password) || strlen($password) < 8) {
        jsonResponse([
            'success' => false,
            'message' => 'La contraseña debe tener al menos 8 caracteres'
        ], 400);
    }
    
    if (!in_array($rol, ['ADMIN', 'USUARIO'])) {
        jsonResponse([
            'success' => false,
            'message' => 'Rol inválido'
        ], 400);
    }
    
    $db = Database::getInstance();
    
    // Verificar si el correo ya existe
    $usuarioExistente = $db->fetchOne(
        "SELECT id FROM usuarios WHERE correo = ?",
        [$correo]
    );
    
    if ($usuarioExistente) {
        jsonResponse([
            'success' => false,
            'message' => 'Ya existe un usuario con ese correo'
        ], 409);
    }
    
    // Hash de la contraseña
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Insertar usuario
    $usuarioId = $db->insert(
        "INSERT INTO usuarios (correo, password, password_visible, nombre_completo, rol, activo) 
         VALUES (?, ?, ?, ?, ?, 1)",
        [$correo, $passwordHash, $password, $nombreCompleto, $rol]
    );
    
    if ($usuarioId) {
        // Registrar quién creó el usuario
        $adminActual = Auth::getCurrentUser();
        
        $db->insert(
            "INSERT INTO usuarios_log 
             (usuario_afectado_id, usuario_afectado_correo, accion_realizada, 
              realizado_por_id, realizado_por_correo, detalles)
             VALUES (?, ?, 'CREACION', ?, ?, ?)",
            [
                $usuarioId,
                $correo,
                $adminActual['id'],
                $adminActual['correo'],
                "Usuario creado con rol: $rol"
            ]
        );
        
        logMessage("Usuario creado: $correo (Rol: $rol) por {$adminActual['correo']}", 'INFO');
        
        jsonResponse([
            'success' => true,
            'message' => 'Usuario creado exitosamente',
            'data' => [
                'id' => $usuarioId,
                'correo' => $correo,
                'nombre_completo' => $nombreCompleto,
                'rol' => $rol
            ]
        ], 201);
    } else {
        jsonResponse([
            'success' => false,
            'message' => 'Error al crear el usuario'
        ], 500);
    }
    
} catch (Exception $e) {
    logMessage('Error en crear_usuario.php: ' . $e->getMessage(), 'ERROR');
    
    jsonResponse([
        'success' => false,
        'message' => 'Error al crear usuario: ' . $e->getMessage()
    ], 500);
}