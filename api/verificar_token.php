<?php
/**
 * TESA Syllabus Monitor
 * API Endpoint: Verificar estado del token OAuth
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

require_once __DIR__ . '/../config/config.php';
require_once INCLUDES_PATH . '/oauth_handler.php';
require_once INCLUDES_PATH . '/functions.php';

try {
    $oauth = new OAuthHandler();
    
    // Si es POST, intentar guardar un token manual
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON inválido');
        }
        
        $access_token = $data['access_token'] ?? null;
        $refresh_token = $data['refresh_token'] ?? '';
        $expires_in = $data['expires_in'] ?? 3600;
        
        if (!$access_token) {
            respuestaJSON(false, null, 'access_token es requerido', 400);
        }
        
        $resultado = $oauth->setManualToken($access_token, $refresh_token, $expires_in);
        
        if ($resultado) {
            logMessage("Token manual guardado exitosamente", 'INFO');
            respuestaJSON(true, $oauth->getTokenInfo(), 'Token guardado correctamente');
        } else {
            throw new Exception('Error al guardar token');
        }
    }
    
    // GET: Verificar estado actual del token
    $token_info = $oauth->getTokenInfo();
      
    $response_data = [
        'token_info' => $token_info,
        'configuracion' => verificarConfiguracion()
    ];
    
    $mensaje = $token_info['is_valid'] 
        ? 'Token válido y configurado correctamente' 
        : 'Token no válido o expirado';
    
    respuestaJSON(true, $response_data, $mensaje);
    
} catch (Exception $e) {
    logMessage("Error en verificar_token.php: " . $e->getMessage(), 'ERROR');
    respuestaJSON(false, null, 'Error al verificar token: ' . $e->getMessage(), 500);
}
