<?php
/**
 * TESA Syllabus Monitor
 * Manejo de OAuth 2.0 para Brightspace
 */

if (!defined('APP_ACCESS')) {
    require_once __DIR__ . '/../config/config.php';
}

require_once CONFIG_PATH . '/database.php';

class OAuthHandler {
    
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $auth_url;
    private $token_url;
    private $scope;
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = Database::getInstance();
        $this->loadCredentials();
    }
    
    /**
     * Cargar credenciales desde la base de datos
     */
    private function loadCredentials() {
        try {
            $this->client_id = getConfig('oauth_client_id', OAUTH_CLIENT_ID);
            $this->client_secret = getConfig('oauth_client_secret', OAUTH_CLIENT_SECRET);
            $this->redirect_uri = getConfig('oauth_redirect_uri', OAUTH_REDIRECT_URI);
            $this->auth_url = getConfig('oauth_auth_url', OAUTH_AUTH_URL);
            $this->token_url = getConfig('oauth_token_url', OAUTH_TOKEN_URL);
            $this->scope = getConfig('oauth_scope', OAUTH_SCOPE);
            
        } catch (Exception $e) {
            logMessage("Error al cargar credenciales OAuth: " . $e->getMessage(), 'ERROR');
            throw new Exception("No se pudieron cargar las credenciales OAuth");
        }
    }
    
    /**
     * Obtener Access Token válido 
     * 
     * @param bool $force_refresh Forzar renovación aunque parezca válido
     * @return string Access Token
     * @throws Exception
     */
    public function getAccessToken($force_refresh = false) {
        // Verificar si hay un token guardado y válido
        $saved_token = getConfig('oauth_access_token');
        $token_expiry = getConfig('oauth_token_expiry');
        
        if ($saved_token && $token_expiry && !$force_refresh) {
            $expiry_time = strtotime($token_expiry);
            $current_time = time();
            
            // Si el token aún es válido (con 5 minutos de margen)
            if ($expiry_time > ($current_time + 300)) {
                logMessage("Usando access token existente (expira: $token_expiry)", 'DEBUG');
                return $saved_token;
            }
        }
        
        // Token expirado o forzar renovación
        logMessage("Token expirado o forzar renovación", 'INFO');
        $refresh_token = getConfig('oauth_refresh_token');
        
        if ($refresh_token) {
            try {
                return $this->refreshAccessToken($refresh_token);
            } catch (Exception $e) {
                logMessage("Error al renovar token: " . $e->getMessage(), 'ERROR');
            }
        }
        
        
        logMessage("No hay token válido. Se requiere autenticación manual.", 'ERROR');
        throw new Exception("No hay Access Token válido. Por favor, autentique la aplicación manualmente.");
    }
    
    /**
     * Renovar Access Token usando Refresh Token
     * 
     * @param string $refresh_token Refresh Token
     * @return string Nuevo Access Token
     * @throws Exception
     */
    public function refreshAccessToken($refresh_token) {
        logMessage("Renovando access token...", 'INFO');
        
        $post_data = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh_token
            // NO incluir client_id y client_secret aquí
            // Se envían en el header Authorization
        ];
        
        try {
            $response = $this->makeTokenRequest($post_data);
            
            if (isset($response['access_token'])) {
                $this->saveTokens(
                    $response['access_token'],
                    $response['refresh_token'] ?? $refresh_token,
                    $response['expires_in'] ?? 3600
                );
                
                logMessage("Access token renovado exitosamente", 'INFO');
                return $response['access_token'];
            }
            
            throw new Exception("Respuesta inválida del servidor OAuth");
            
        } catch (Exception $e) {
            logMessage("Error al renovar token: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Obtener Access Token usando código de autorización
     * (Para uso manual desde Postman o flujo OAuth completo)
     * 
     * @param string $auth_code Código de autorización
     * @return string Access Token
     * @throws Exception
     */
    public function getAccessTokenFromCode($auth_code) {
        logMessage("Obteniendo access token desde código de autorización", 'INFO');
        
        $post_data = [
            'grant_type' => 'authorization_code',
            'code' => $auth_code,
            'redirect_uri' => $this->redirect_uri,
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret
        ];
        
        try {
            $response = $this->makeTokenRequest($post_data);
            
            if (isset($response['access_token'])) {
                $this->saveTokens(
                    $response['access_token'],
                    $response['refresh_token'] ?? '',
                    $response['expires_in'] ?? 3600
                );
                
                logMessage("Access token obtenido exitosamente", 'INFO');
                return $response['access_token'];
            }
            
            throw new Exception("Respuesta inválida del servidor OAuth");
            
        } catch (Exception $e) {
            logMessage("Error al obtener token: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Guardar token manualmente (desde Postman)
     * 
     * @param string $access_token Access Token
     * @param string $refresh_token Refresh Token
     * @param int $expires_in Tiempo de expiración en segundos
     * @return bool
     */
    public function setManualToken($access_token, $refresh_token = '', $expires_in = 3600) {
        return $this->saveTokens($access_token, $refresh_token, $expires_in);
    }
    
    /**
     * Realizar petición para obtener token
     * 
     * @param array $post_data Datos POST
     * @return array Respuesta decodificada
     * @throws Exception
     */
    private function makeTokenRequest($post_data) {
        $ch = curl_init($this->token_url);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($post_data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ],
            CURLOPT_USERPWD => $this->client_id . ':' . $this->client_secret,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            throw new Exception("Error cURL: $curl_error");
        }
        
        if ($http_code !== 200) {
            $error_data = json_decode($response, true);
            $error_msg = $error_data['error_description'] ?? $error_data['error'] ?? 'Error desconocido';
            logMessage("Respuesta del servidor (HTTP $http_code): " . $response, 'DEBUG');
            throw new Exception("Error HTTP $http_code: $error_msg");
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Error al decodificar respuesta JSON");
        }
        
        return $data;
    }
    
    /**
     * Guardar tokens en la base de datos
     * 
     * @param string $access_token Access Token
     * @param string $refresh_token Refresh Token
     * @param int $expires_in Tiempo de expiración en segundos
     * @return bool
     */
    private function saveTokens($access_token, $refresh_token, $expires_in) {
        try {
            $expiry_date = date('Y-m-d H:i:s', time() + $expires_in);
            
            setConfig('oauth_access_token', $access_token);
            setConfig('oauth_token_expiry', $expiry_date);
            
            if (!empty($refresh_token)) {
                setConfig('oauth_refresh_token', $refresh_token);
            }
            
            logMessage("Tokens guardados. Expiran: $expiry_date", 'INFO');
            return true;
            
        } catch (Exception $e) {
            logMessage("Error al guardar tokens: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Verificar si hay un token válido
     * 
     * @return bool
     */
    public function hasValidToken() {
        try {
            $token = getConfig('oauth_access_token');
            $expiry = getConfig('oauth_token_expiry');
            
            if (!$token || !$expiry) {
                return false;
            }
            
            $expiry_time = strtotime($expiry);
            $current_time = time();
            
            return $expiry_time > ($current_time + 300); // 5 minutos de margen
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Obtener URL de autorización (para flujo OAuth manual)
     * 
     * @param string $state Estado para CSRF protection
     * @return string URL de autorización
     */
    public function getAuthorizationUrl($state = null) {
        $state = $state ?? bin2hex(random_bytes(16));
        
        $params = [
            'response_type' => 'code',
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'scope' => $this->scope,
            'state' => $state
        ];
        
        return $this->auth_url . '?' . http_build_query($params);
    }
    
    /**
     * Revocar token actual (logout)
     * 
     * @return bool
     */
    public function revokeToken() {
        try {
            setConfig('oauth_access_token', '');
            setConfig('oauth_refresh_token', '');
            setConfig('oauth_token_expiry', '');
            
            logMessage("Tokens revocados", 'INFO');
            return true;
            
        } catch (Exception $e) {
            logMessage("Error al revocar tokens: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Obtener información del token actual
     * 
     * @return array
     */
    public function getTokenInfo() {
        return [
            'has_token' => !empty(getConfig('oauth_access_token')),
            'is_valid' => $this->hasValidToken(),
            'expires_at' => getConfig('oauth_token_expiry'),
            'has_refresh_token' => !empty(getConfig('oauth_refresh_token'))
        ];
    }
}