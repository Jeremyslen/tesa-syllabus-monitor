<?php
/**
 * TESA Syllabus Monitor - API Brightspace Handler
 * Consumo de APIs de Brightspace (Procesamiento Secuencial)
 */

if (!defined('APP_ACCESS')) {
    require_once __DIR__ . '/../config/config.php';
}

require_once INCLUDES_PATH . '/oauth_handler.php';

class ApiBrightspace {
    
    private $oauth;
    private $base_url;
    private $access_token;
    
    public function __construct() {
        set_time_limit(600);
        ini_set('default_socket_timeout', 600);
        $this->oauth = new OAuthHandler();
        $this->base_url = API_BASE_URL;
        
        try {
            $this->access_token = $this->oauth->getAccessToken();
        } catch (Exception $e) {
            logMessage("Error al obtener access token: " . $e->getMessage(), 'ERROR');
            throw new Exception("No se pudo autenticar con Brightspace.");
        }
    }
    
    // =========================================================================
    // MANEJO DE PETICIONES HTTP
    // =========================================================================

    /**
     * Realizar petición GET a la API con soporte para paginación y reintentos
     */
    private function makeRequest($endpoint, $params = [], $retry_count = 0) {
        if ($retry_count > 1) {
            throw new Exception("Máximo de reintentos alcanzado.");
        }
        
        $url = $this->base_url . $endpoint;
        
        if (!empty($params)) {
            $url .= (strpos($url, '?') !== false ? '&' : '?') . http_build_query($params);
        }
        
        logMessage("Petición API: $url" . ($retry_count > 0 ? " (reintento $retry_count)" : ""), 'DEBUG');
        
        $ch = curl_init($url);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->access_token,
                'Accept: application/json',
                'Content-Type: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => API_TIMEOUT
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            logMessage("Error cURL: $curl_error", 'ERROR');
            throw new Exception("Error de conexión: $curl_error");
        }
        
        // Manejo de token expirado (401)
        if ($http_code === 401 && $retry_count === 0) {
            logMessage("Token inválido (401), renovando...", 'INFO');
            try {
                $this->access_token = $this->oauth->getAccessToken(true);
                return $this->makeRequest($endpoint, $params, $retry_count + 1);
            } catch (Exception $e) {
                throw new Exception("Token de autenticación inválido.");
            }
        }
        
        // Manejo de otros códigos HTTP
        if ($http_code === 401) throw new Exception("Token inválido incluso después de renovar");
        if ($http_code === 403) throw new Exception("Acceso denegado");
        if ($http_code === 404) {
            logMessage("Recurso no encontrado (404): $url", 'WARNING');
            return ['data' => [], 'paging_info' => null];
        }
        if ($http_code !== 200) throw new Exception("Error HTTP $http_code al consultar la API");
        
        // Procesar respuesta
        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Error al procesar respuesta de la API");
        }
        
        return [
            'data' => $data,
            'paging_info' => $this->extractPagingInfo($header)
        ];
    }
    
    /**
     * Extraer información de paginación de los headers HTTP
     */
    private function extractPagingInfo($headers) {
        $paging_info = ['has_more' => false, 'bookmark' => null];
        
        if (preg_match('/X-Paging-Info:\s*(.+)/i', $headers, $matches)) {
            $paging_data = json_decode(trim($matches[1]), true);
            if ($paging_data && isset($paging_data['HasMoreItems'])) {
                $paging_info['has_more'] = $paging_data['HasMoreItems'];
                $paging_info['bookmark'] = $paging_data['Bookmark'] ?? null;
            }
        }
        
        return $paging_info;
    }
    

    // =========================================================================
    // CONSULTAS PRINCIPALES - PERÍODOS Y CLASES
    // =========================================================================
    
    /**
     * Obtener todos los períodos/semestres
     */
    public function getPeriodos() {
        try {
            logMessage("Obteniendo períodos...", 'INFO');
            $result = $this->makeRequest(API_PERIODOS_ENDPOINT);
            logMessage("Períodos obtenidos: " . count($result['data']), 'INFO');
            return $result['data'];
        } catch (Exception $e) {
            logMessage("Error al obtener períodos: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Obtener TODAS las clases de un período con paginación automática
     */
    public function getClasesPorPeriodo($semesterId) {
        try {
            logMessage("Obteniendo clases del período: $semesterId (con paginación)", 'INFO');
            
            $endpoint = str_replace('{semesterId}', $semesterId, API_CLASES_ENDPOINT);
            $todas_las_clases = [];
            $bookmark = null;
            $page = 1;
            
            do {
                $params = $bookmark ? ['bookmark' => $bookmark] : [];
                $params['pageSize'] = 500;
                
                logMessage("Obteniendo página $page" . ($bookmark ? " (bookmark: $bookmark)" : ""), 'DEBUG');
                
                $result = $this->makeRequest($endpoint, $params);
                $clases = $result['data'];
                $paging_info = $result['paging_info'];
                
                if (!empty($clases)) {
                    $todas_las_clases = array_merge($todas_las_clases, $clases);
                    logMessage("Página $page: " . count($clases) . " clases. Total: " . count($todas_las_clases), 'INFO');
                } else {
                    logMessage("Página $page: sin clases, finalizando", 'DEBUG');
                    break;
                }
                
                if ($paging_info && $paging_info['has_more'] && $paging_info['bookmark']) {
                    $bookmark = $paging_info['bookmark'];
                    $page++;
                } else {
                    $bookmark = null;
                }
                
                if ($page > 100) {
                    logMessage("Límite de 100 páginas alcanzado", 'WARNING');
                    break;
                }
                
            } while ($bookmark !== null);
            
            logMessage("✅ Total de clases obtenidas: " . count($todas_las_clases), 'INFO');
            return $todas_las_clases;
            
        } catch (Exception $e) {
            logMessage("Error al obtener clases del período $semesterId: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    
    // =========================================================================
    // CONSULTAS DE CONTENIDO Y CALIFICACIONES
    // =========================================================================
    
    /**
     * Obtener contenido/archivos de una clase
     */
    public function getContenido($orgUnitId) {
        try {
            $endpoint = str_replace('{orgUnitId}', $orgUnitId, API_CONTENIDO_ENDPOINT);
            $result = $this->makeRequest($endpoint);
            $data = $result['data'];
            
            $total_docs = 0;
            if (isset($data['Modules'])) {
                foreach ($data['Modules'] as $module) {
                    if (isset($module['Topics'])) {
                        foreach ($module['Topics'] as $topic) {
                            if ($topic['TypeIdentifier'] === 'File') {
                                $total_docs++;
                            }
                        }
                    }
                }
            }
            
            logMessage("Contenido obtenido de $orgUnitId. Documentos: $total_docs", 'DEBUG');
            return $data;
            
        } catch (Exception $e) {
            $nivel = (strpos($e->getMessage(), 'Acceso denegado') !== false) ? 'DEBUG' : 'WARNING';
            logMessage("Error al obtener contenido de $orgUnitId: " . $e->getMessage(), $nivel);
            throw $e;
        }
    }
    
    /**
     * Obtener estructura de calificaciones (grade items) de una clase
     */
    public function getCalificaciones($orgUnitId) {
        try {
            $endpoint = str_replace('{orgUnitId}', $orgUnitId, API_CALIFICACIONES_ENDPOINT);
            $result = $this->makeRequest($endpoint);
            
            logMessage("Calificaciones obtenidas de $orgUnitId: " . count($result['data']), 'DEBUG');
            return $result['data'];
            
        } catch (Exception $e) {
            $nivel = (strpos($e->getMessage(), 'Acceso denegado') !== false) ? 'DEBUG' : 'WARNING';
            logMessage("Error al obtener calificaciones de $orgUnitId: " . $e->getMessage(), $nivel);
            throw $e;
        }
    }
    
    /**
     * Obtener categorías de calificaciones de una clase
     */
    public function getCategoriasCalificaciones($orgUnitId) {
        try {
            $endpoint = str_replace('{orgUnitId}', $orgUnitId, '/d2l/api/le/1.43/{orgUnitId}/grades/categories/');
            $result = $this->makeRequest($endpoint);
            
            logMessage("Categorías obtenidas de $orgUnitId: " . count($result['data']), 'DEBUG');
            return $result['data'];
            
        } catch (Exception $e) {
            $nivel = (strpos($e->getMessage(), 'Acceso denegado') !== false) ? 'DEBUG' : 'WARNING';
            logMessage("Error al obtener categorías de $orgUnitId: " . $e->getMessage(), $nivel);
            return [];
        }
    }

    /**
     * Verificar si existe un anuncio de Bienvenida en la clase
     */
    public function getTieneBienvenida($orgUnitId) {
        try {
            $endpoint = '/d2l/api/le/1.43/' . $orgUnitId . '/news/';
            $result = $this->makeRequest($endpoint);
            $noticias = $result['data'];
            
            if (empty($noticias)) {
                return 'NO';
            }
            
            foreach ($noticias as $noticia) {   
                $titulo = $noticia['Title'] ?? '';
                if (stripos($titulo, 'bienvenida') !== false || stripos($titulo, 'bienvenidos') !== false) {
                    return 'SI';
                }
            }
            
            return 'NO';
            
        } catch (Exception $e) {
            $nivel = (strpos($e->getMessage(), 'Acceso denegado') !== false) ? 'DEBUG' : 'WARNING';
            logMessage("Error al obtener announcements de $orgUnitId: " . $e->getMessage(), $nivel);
            return 'NO';
        }
    }
        
    // =========================================================================
    // PROCESAMIENTO DE DATOS
    // =========================================================================
    
    /**
     * Procesar contenido para detectar Syllabus y contar documentos
     */
    public function procesarContenido($contenido) {
        $tiene_syllabus = 'NO';
        $total_documentos = 0;
        
        if (!isset($contenido['Modules']) || empty($contenido['Modules'])) {
            return ['tiene_syllabus' => 'NO', 'total_documentos' => 0];
        }
        
        foreach ($contenido['Modules'] as $module) {
            if (stripos($module['Title'], 'syllabus') !== false) {
                $tiene_syllabus = 'SI';
            }
            
            if (isset($module['Topics']) && is_array($module['Topics'])) {
                foreach ($module['Topics'] as $topic) {
                    if ($topic['TypeIdentifier'] === 'File') {
                        $total_documentos++;
                        if (stripos($topic['Title'], 'syllabus') !== false) {
                            $tiene_syllabus = 'SI';
                        }
                    }
                }
            }
        }
        
        return [
            'tiene_syllabus' => $tiene_syllabus,
            'total_documentos' => $total_documentos
        ];
    }
    
    public function calcularCalificacionFinal($calificaciones, $categorias = []) {
        $total = 0.0;
        
        if (empty($calificaciones)) {
            return 0.0;
        }
        
        logMessage("Calculando calificación final de " . count($calificaciones) . " items", 'DEBUG');
        
        $categorias_excluidas = [];
        if (!empty($categorias)) {
            foreach ($categorias as $cat) {
                if (isset($cat['ExcludeFromFinalGrade']) && $cat['ExcludeFromFinalGrade'] === true) {
                    $categorias_excluidas[] = $cat['Id'];
                    logMessage("Categoría excluida detectada: {$cat['Name']} (ID: {$cat['Id']})", 'DEBUG');
                }
            }
        }
        
        foreach ($calificaciones as $item) {
            $nombre = $item['Name'] ?? '';
            $max_points = floatval($item['MaxPoints'] ?? 0);
            $category_id = $item['CategoryId'] ?? null;
            $grade_type = $item['GradeType'] ?? 'Numeric';
            
            $es_categoria_calculada = ($grade_type === 'Calculated');
            $es_categoria_sin_puntos = ($max_points == 0);
            $es_bonus = ($item['IsBonus'] ?? false) === true;
            $item_excluido = ($item['ExcludeFromFinalGradeCalculation'] ?? false) === true;
            $categoria_excluida = $category_id && in_array($category_id, $categorias_excluidas);
            
            $incluir = !$es_categoria_calculada 
                    && !$es_categoria_sin_puntos
                    && !$item_excluido
                    && !$categoria_excluida
                    && !$es_bonus 
                    && $max_points > 0;
            
            if ($incluir) {
                $total += $max_points;
                logMessage("✓ Incluido: $nombre = $max_points pts (Total: $total)", 'DEBUG');
            } else {
                $razon = '';
                if ($es_categoria_calculada) $razon = 'categoría calculada';
                elseif ($es_categoria_sin_puntos) $razon = 'sin puntos';
                elseif ($item_excluido) $razon = 'excluido por item';
                elseif ($categoria_excluida) $razon = 'excluido por categoría';
                elseif ($es_bonus) $razon = 'bonus';
                
                logMessage("✗ Excluido: $nombre [$razon]", 'DEBUG');
            }
        }
        
        logMessage("✅ Calificación final calculada: $total puntos", 'INFO');
        return round($total, 2);
    }

    // =========================================================================
    // OBTENCIÓN DE DATOS COMPLETOS DE UNA CLASE
    // =========================================================================

    public function getDatosCompletosClase($orgUnitId, $nombre_clase = '') {
        try {
            $resultado = [
                'org_unit_id'          => $orgUnitId,
                'tiene_syllabus'       => 'PENDIENTE',
                'calificacion_final'   => 0.0,
                'total_documentos'     => 0,
                'tiene_bienvenida'     => 'NO',
                'error'                => null
            ];
            
            $es_grupo = preg_match('/^(group|grupo|team|equipo)\s+\d+$/i', trim($nombre_clase));
            
            // 1. Obtener y procesar contenido
            try {
                $contenido = $this->getContenido($orgUnitId);
                $datos_contenido = $this->procesarContenido($contenido);
                $resultado['tiene_syllabus']   = $datos_contenido['tiene_syllabus'];
                $resultado['total_documentos'] = $datos_contenido['total_documentos'];
            } catch (Exception $e) {
                if ($es_grupo && strpos($e->getMessage(), 'Acceso denegado') !== false) {
                    logMessage("Grupo de trabajo sin acceso: $nombre_clase (ID: $orgUnitId)", 'DEBUG');
                } else {
                    logMessage("⚠️ Error al procesar contenido de $orgUnitId: " . $e->getMessage(), 'WARNING');
                }
                $resultado['error'] = 'Error en contenido';
            }
            
            // 2. Obtener calificaciones y categorías
            try {
                $calificaciones = $this->getCalificaciones($orgUnitId);
                $categorias     = $this->getCategoriasCalificaciones($orgUnitId);
                $resultado['calificacion_final'] = $this->calcularCalificacionFinal($calificaciones, $categorias);
                logMessage("🎯 Calificación calculada para $orgUnitId: {$resultado['calificacion_final']}", 'DEBUG');
            } catch (Exception $e) {
                if ($es_grupo && strpos($e->getMessage(), 'Acceso denegado') !== false) {
                    logMessage("Grupo de trabajo sin calificaciones: $nombre_clase (ID: $orgUnitId)", 'DEBUG');
                } else {
                    logMessage("⚠️ Error al procesar calificaciones de $orgUnitId: " . $e->getMessage(), 'WARNING');
                }
                $resultado['error'] = $resultado['error'] 
                    ? $resultado['error'] . ', Error en calificaciones' 
                    : 'Error en calificaciones';
            }

            // 3. Verificar anuncio de bienvenida
            try {
                $resultado['tiene_bienvenida'] = $this->getTieneBienvenida($orgUnitId);
                logMessage("📢 Bienvenida en $orgUnitId: {$resultado['tiene_bienvenida']}", 'DEBUG');
            } catch (Exception $e) {
                logMessage("⚠️ Error al verificar bienvenida de $orgUnitId: " . $e->getMessage(), 'WARNING');
                $resultado['tiene_bienvenida'] = 'NO';
            }
            
            return $resultado;
            
        } catch (Exception $e) {
            logMessage("❌ Error al obtener datos completos de $orgUnitId: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
}