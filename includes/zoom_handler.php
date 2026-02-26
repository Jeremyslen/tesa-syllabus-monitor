<?php
/**
 * TESA Syllabus Monitor - Zoom API Handler
 */

if (!defined('APP_ACCESS')) {
    require_once __DIR__ . '/../config/config.php';
}

require_once CONFIG_PATH . '/zoom_config.php';

class ZoomHandler {

    private $access_token = null;

    // =========================================================================
    // AUTENTICACIÓN - OAuth Server-to-Server
    // =========================================================================

    private function getAccessToken() {
        if ($this->access_token) {
            return $this->access_token;
        }

        $credentials = base64_encode(ZOOM_CLIENT_ID . ':' . ZOOM_CLIENT_SECRET);

        $ch = curl_init(ZOOM_TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type' => 'account_credentials',
                'account_id' => ZOOM_ACCOUNT_ID
            ]),
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . $credentials,
                'Content-Type: application/x-www-form-urlencoded'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 30
        ]);

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            logMessage("Error al obtener token Zoom (HTTP $http_code): $response", 'ERROR');
            throw new Exception("No se pudo autenticar con Zoom API");
        }

        $data = json_decode($response, true);
        $this->access_token = $data['access_token'] ?? null;

        if (!$this->access_token) {
            throw new Exception("Token Zoom vacío en la respuesta");
        }

        logMessage("✅ Token Zoom obtenido correctamente", 'DEBUG');
        return $this->access_token;
    }

    // =========================================================================
    // PETICIONES HTTP
    // =========================================================================

    private function makeRequest($endpoint, $params = []) {
        $token = $this->getAccessToken();
        $url   = ZOOM_API_BASE_URL . $endpoint;

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        logMessage("Zoom API: $url", 'DEBUG');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 30
        ]);

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 404) {
            return null; // Usuario o recurso no encontrado
        }

        if ($http_code !== 200) {
            logMessage("Error Zoom API HTTP $http_code: $response", 'WARNING');
            throw new Exception("Error Zoom API HTTP $http_code");
        }

        return json_decode($response, true);
    }

    // =========================================================================
    // DETECCIÓN DE REUNIONES
    // =========================================================================

    /**
     * Verificar si un usuario (por email) tiene reuniones programadas en Zoom
     */
    public function tieneMeetingsActivos($email) {
        try {
            if (empty($email)) {
                return 'NO';
            }

            // Buscar reuniones programadas del usuario
            $data = $this->makeRequest('/users/' . urlencode($email) . '/meetings', [
                'type'      => 'scheduled',
                'page_size' => 1
            ]);

            if ($data === null) {
                logMessage("Usuario Zoom no encontrado: $email", 'DEBUG');
                return 'NO';
            }

            $total = $data['total_records'] ?? 0;
            $tiene = $total > 0 ? 'SI' : 'NO';

            logMessage("📹 Zoom meetings para $email: $total → $tiene", 'DEBUG');
            return $tiene;

        } catch (Exception $e) {
            logMessage("Error al verificar Zoom para $email: " . $e->getMessage(), 'WARNING');
            return 'NO';
        }
    }
}