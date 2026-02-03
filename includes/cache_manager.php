<?php
/**
 * TESA Syllabus Monitor - Cache Manager
 */

if (!defined('APP_ACCESS')) {
    require_once __DIR__ . '/../config/config.php';
}

require_once CONFIG_PATH . '/database.php';
require_once INCLUDES_PATH . '/api_brightspace.php';

class CacheManager {
    
    private $db;
    private $api;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->api = new ApiBrightspace();
    }
    

    // GESTIÓN DE PERÍODOS

    
    /**
     * Obtener o crear período en la base de datos
     */
    public function guardarPeriodo($periodo_data) {
        try {
            $org_unit_id = intval($periodo_data['Identifier']);
            $codigo = trim($periodo_data['Code']);
            $nombre = trim($periodo_data['Name']);
            
            // Verificar si ya existe el período
            $existente = $this->db->fetchOne(
                "SELECT id FROM periodos WHERE org_unit_id = ? LIMIT 1",
                [$org_unit_id]
            );
            
            if ($existente) {
                // Actualizar período existente
                $this->db->update(
                    "UPDATE periodos SET codigo = ?, nombre = ?, fecha_actualizacion = NOW() WHERE id = ?",
                    [$codigo, $nombre, $existente['id']]
                );
                return (int)$existente['id'];
            } else {
                // Insertar nuevo período
                return $this->db->insert(
                    "INSERT INTO periodos (org_unit_id, codigo, nombre, activo) VALUES (?, ?, ?, 1)",
                    [$org_unit_id, $codigo, $nombre]
                );
            }
            
        } catch (Exception $e) {
            logMessage("Error al guardar período: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
   
    // FILTRADO DE GRUPOS DE TRABAJO
   
    
    /**
     * Verificar si una clase es un grupo de trabajo que debe ser ignorado
     */
    private function esGrupoDeTrabajo($nombre) {
        $nombre_lower = strtolower(trim($nombre));
        
        // Patrones para identificar grupos de trabajo
        $patrones_grupos = [
            '/^group\s+\d+$/i',
            '/^grupo\s+\d+$/i',
            '/^team\s+\d+$/i',
            '/^equipo\s+\d+$/i',
            '/^section\s+\d+$/i',
            '/^sección\s+\d+$/i',
            '/^equipo\s+\d+\s*-/i',
            '/^team\s+\d+\s*-/i',
            '/^grupo\s+\d+\s*-/i',
            '/^h\d+\s+\d+$/i',
            '/^\d+\.\w+\.[a-z0-9\-]+\.\w+\.\w+\.\d+\s+\d+$/i',
            '/^(profe|profesor|profesora|teacher|docente)\s+\w+\s+\d+$/i',
            '/^grupo\s+(profe|profesor|profesora)\s+\w+(\s+\w+)?\s+\d+$/i',
            '/^actividad\s+\d+\s+\d+$/i',
            '/^activity\s+\d+\s+\d+$/i',
            '/^tarea\s+\d+\s+\d+$/i',
            '/^assignment\s+\d+\s+\d+$/i',
            '/^\d+\s+\d+$/i',
            '/^\d+\.\s*\d+$/i',
            '/^\d+$/i',
        ];
        
        foreach ($patrones_grupos as $patron) {
            if (preg_match($patron, $nombre)) {
                return true;
            }
        }
        
        return false;
    }
    
 
    // GESTIÓN DE CLASES

    /**
     * Guardar o actualizar clase en la base de datos
     */
    public function guardarClase($clase_data, $periodo_id) {
        try {
            if (!isset($clase_data['Identifier']) || !isset($clase_data['Name']) || !isset($clase_data['Code'])) {
                throw new Exception("Datos de clase incompletos");
            }
            
            $org_unit_id = intval($clase_data['Identifier']);
            $codigo_clase = trim($clase_data['Code']);
            $nombre_api = trim($clase_data['Name']);
            
            // Construir nombre completo consistente
            if (strpos($nombre_api, ' - ') !== false) {
                $nombre_completo = $nombre_api;
            } else {
                if (!empty($codigo_clase) && $codigo_clase !== $nombre_api) {
                    $nombre_completo = $codigo_clase . ' - ' . $nombre_api;
                } else {
                    $nombre_completo = $nombre_api;
                }
            }
            
            logMessage("📝 Procesando clase: API='$nombre_api' | Code='$codigo_clase' | Final='$nombre_completo'", 'DEBUG');
            
            // Extraer información de la clase
            $nrc = extraerNRC($nombre_completo);
            $codigo_carrera = extraerCodigoCarrera($nombre_completo);
            $carrera_id = $codigo_carrera ? obtenerIdCarrera($codigo_carrera) : null;
            
            // Verificar si ya existe la clase
            $existente = $this->db->fetchOne(
                "SELECT id FROM clases WHERE org_unit_id = ? LIMIT 1",
                [$org_unit_id]
            );
            
            if ($existente) {
                // Actualizar clase existente
                $this->db->update(
                    "UPDATE clases SET 
                        periodo_id = ?, 
                        carrera_id = ?, 
                        nrc = ?, 
                        nombre_completo = ?,
                        codigo_clase = ?,
                        fecha_actualizacion = NOW()
                    WHERE id = ?",
                    [$periodo_id, $carrera_id, $nrc, $nombre_completo, $codigo_clase, $existente['id']]
                );
                logMessage("✅ Clase actualizada: $nombre_completo (ID: {$existente['id']})", 'DEBUG');
                return (int)$existente['id'];
            } else {
                // Insertar nueva clase
                $clase_id = $this->db->insert(
                    "INSERT INTO clases (
                        org_unit_id, periodo_id, carrera_id, nrc, nombre_completo, codigo_clase
                    ) VALUES (?, ?, ?, ?, ?, ?)",
                    [$org_unit_id, $periodo_id, $carrera_id, $nrc, $nombre_completo, $codigo_clase]
                );
                logMessage("✅ Clase NUEVA guardada: $nombre_completo (ID: $clase_id)", 'DEBUG');
                return $clase_id;
            }
            
        } catch (Exception $e) {
            logMessage("Error al guardar clase: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    

    // SINCRONIZACIÓN SECUENCIAL 
    
    /**
     * Sincronizar todas las clases de un período - PROCESAMIENTO SECUENCIAL 1x1
     */
    public function sincronizarClasesPeriodo($periodo_id, $org_unit_id, $forzar_actualizacion = false, $carrera_codigo = null) {
        $inicio = microtime(true);
        $resultado = [
            'total' => 0,
            'nuevas' => 0,
            'actualizadas' => 0,
            'errores' => 0,
            'ignoradas' => 0,
            'duracion' => 0
        ];
        
        try {
            $log_carrera = $carrera_codigo ? " (Filtrando carrera: $carrera_codigo)" : "";
            logMessage("🔄 Iniciando sincronización SECUENCIAL de período ID: $periodo_id$log_carrera", 'INFO');
            
            // PASO 1: Obtener clases desde el API
            $clases_api = $this->api->getClasesPorPeriodo($org_unit_id);
            $total_api = count($clases_api);
            logMessage("📥 Clases obtenidas del API: $total_api", 'INFO');
            
            if ($total_api === 0) {
                logMessage("⚠️ No se encontraron clases en el API", 'WARNING');
                return $resultado;
            }
            
            // 🆕 NUEVO: Filtrar por carrera si se especificó
            if ($carrera_codigo) {
                $clases_api = array_filter($clases_api, function($clase) use ($carrera_codigo) {
                    $nombre = $clase['Name'] ?? '';
                    $codigo = $clase['Code'] ?? '';
                    
                    // Buscar patrón como: "25.S3.ADM4010" o "25.S3.ADM-4001"
                    $patron = '/\.' . preg_quote($carrera_codigo, '/') . '[-\d]/i';
                    
                    return preg_match($patron, $nombre) || preg_match($patron, $codigo);
                });
                
                // Re-indexar array después del filtro
                $clases_api = array_values($clases_api);
                
                $total_filtradas = count($clases_api);
                logMessage("🔍 Clases filtradas por carrera $carrera_codigo: $total_filtradas de $total_api", 'INFO');
                
                if ($total_filtradas === 0) {
                    logMessage("⚠️ No se encontraron clases para la carrera $carrera_codigo", 'WARNING');
                    return $resultado;
                }
                
                // Actualizar el total para los cálculos de progreso
                $total_api = $total_filtradas;
            }
            
            // PASO 2: Procesar cada clase SECUENCIALMENTE
            $clases_procesadas = 0;
            $clases_actualizadas_count = 0;
            
            foreach ($clases_api as $clase_data) {
                try {
                    if (!isset($clase_data['Identifier'])) {
                        $resultado['errores']++;
                        continue;
                    }
                    
                    $nombre = $clase_data['Name'] ?? 'DESCONOCIDA';
                    $org_unit_clase = intval($clase_data['Identifier']);
                    
                    // Filtrar grupos de trabajo
                    if ($this->esGrupoDeTrabajo($nombre)) {
                        $resultado['ignoradas']++;
                        continue;
                    }
                    
                    // Guardar información básica de la clase
                    $clase_id = $this->guardarClase($clase_data, $periodo_id);
                    $resultado['total']++;
                    
                    // Determinar si necesita actualización
                    $necesita_actualizacion = false;
                    
                    if ($forzar_actualizacion) {
                        $necesita_actualizacion = true;
                    } else {
                        $clase_info = $this->db->fetchOne(
                            "SELECT fecha_actualizacion, tiene_syllabus FROM clases WHERE id = ?",
                            [$clase_id]
                        );
                        
                        if ($clase_info) {
                            $necesita_actualizacion = ($clase_info['tiene_syllabus'] === 'PENDIENTE') || 
                                                     cacheVencido($clase_info['fecha_actualizacion']);
                        }
                    }
                    
                    // Actualizar datos completos si es necesario
                    if ($necesita_actualizacion) {
                        // Obtener datos completos del API
                        $datos = $this->api->getDatosCompletosClase($org_unit_clase, $nombre);
                        
                        // Convertir a tipos correctos
                        $calificacion = (float)$datos['calificacion_final'];
                        $tiene_syllabus = $datos['tiene_syllabus'];
                        $total_docs = (int)$datos['total_documentos'];
                        
                        // Actualizar en BD
                        $filas_afectadas = $this->db->update(
                            "UPDATE clases SET 
                                tiene_syllabus = ?,
                                calificacion_final = ?,
                                total_documentos = ?,
                                fecha_actualizacion = NOW()
                            WHERE id = ?",
                            [$tiene_syllabus, $calificacion, $total_docs, $clase_id]
                        );
                        
                        if ($filas_afectadas > 0) {
                            $clases_actualizadas_count++;
                        }
                    }
                    
                    $clases_procesadas++;
                    
                   
                    if ($clases_procesadas % 20 === 0) {
                        $porcentaje = round(($clases_procesadas / $total_api) * 100);
                        logMessage("Progreso: {$clases_procesadas}/{$total_api} ({$porcentaje}%)", 'INFO');
                        
                        
                        gc_collect_cycles();
                    }
                    
            
                    usleep(30000); // 30ms
                    
                } catch (Exception $e) {
                    $resultado['errores']++;
                    logMessage("❌ Error procesando clase: " . $e->getMessage(), 'WARNING');
                }
            }
            
            // PASO 3: Finalizar proceso
            $resultado['actualizadas'] = $clases_actualizadas_count;
            $resultado['nuevas'] = $resultado['total'] - $resultado['actualizadas'];
            $resultado['duracion'] = round(microtime(true) - $inicio, 2);
            
            setConfig('ultima_sincronizacion', date('Y-m-d H:i:s'));
            
            logMessage(
                "✅ Sincronización completada. Total: {$resultado['total']}, " .
                "Actualizadas: {$resultado['actualizadas']}, Errores: {$resultado['errores']}, " .
                "Duración: {$resultado['duracion']}s", 
                'INFO'
            );
            
            $this->registrarLogSincronizacion('CLASES', $periodo_id, $resultado);
            return $resultado;
            
        } catch (Exception $e) {
            $resultado['duracion'] = round(microtime(true) - $inicio, 2);
            logMessage("❌ Error en sincronización: " . $e->getMessage(), 'ERROR');
            $this->registrarLogSincronizacion('CLASES', $periodo_id, $resultado, $e->getMessage());
            throw $e;
        }
    }
    

    // CONSULTAS DE CACHE
    
    /**
     * Obtener clases desde cache con filtros
     */
    public function obtenerClasesDesdeCache($periodo_id, $carrera_codigo = null) {
        try {
            $sql = "SELECT 
                        c.id, c.org_unit_id, c.nrc, c.nombre_completo,
                        c.tiene_syllabus, c.calificacion_final, c.total_documentos,
                        c.fecha_actualizacion, car.codigo as carrera_codigo,
                        car.nombre as carrera_nombre
                    FROM clases c
                    LEFT JOIN carreras car ON c.carrera_id = car.id
                    WHERE c.periodo_id = ?";
            
            $params = [$periodo_id];
            
            if ($carrera_codigo) {
                $sql .= " AND car.codigo = ?";
                $params[] = $carrera_codigo;
            }
            
            $sql .= " ORDER BY c.nombre_completo ASC";
            
            return $this->db->fetchAll($sql, $params);
            
        } catch (Exception $e) {
            logMessage("Error al obtener clases desde cache: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Obtener el ID interno del período por su org_unit_id
     */
    public function obtenerIdPeriodoPorOrgUnit($org_unit_id) {
        try {
            $periodo = $this->db->fetchOne(
                "SELECT id FROM periodos WHERE org_unit_id = ? LIMIT 1",
                [$org_unit_id]
            );
            
            return $periodo ? (int)$periodo['id'] : null;
            
        } catch (Exception $e) {
            logMessage("Error al obtener ID del período: " . $e->getMessage(), 'ERROR');
            return null;
        }
    }
    

    // MANTENIMIENTO

    /**
     * Limpiar cache antiguo (más de X horas)
     */
    public function limpiarCacheAntiguo($horas = 24) {
        try {
            $fecha_limite = date('Y-m-d H:i:s', strtotime("-$horas hours"));
            
            $eliminados_contenido = $this->db->delete(
                "DELETE FROM contenido_cache WHERE fecha_cache < ?",
                [$fecha_limite]
            );
            
            $eliminados_calificaciones = $this->db->delete(
                "DELETE FROM calificaciones_cache WHERE fecha_cache < ?",
                [$fecha_limite]
            );
            
            $total_eliminados = $eliminados_contenido + $eliminados_calificaciones;
            
            if ($total_eliminados > 0) {
                logMessage("🧹 Cache antiguo limpiado: $total_eliminados registros", 'INFO');
            }
            
            return $total_eliminados;
            
        } catch (Exception $e) {
            logMessage("Error al limpiar cache: " . $e->getMessage(), 'ERROR');
            return 0;
        }
    }
    
    /**
     * Registrar log de sincronización
     */
    private function registrarLogSincronizacion($tipo, $periodo_id, $resultado, $errores = null) {
        try {
            $this->db->insert(
                "INSERT INTO logs_sincronizacion (
                    tipo_sincronizacion, periodo_id, total_registros,
                    registros_exitosos, registros_fallidos, errores,
                    duracion_segundos, fecha_fin
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $tipo,
                    $periodo_id,
                    $resultado['total'],
                    $resultado['nuevas'] + $resultado['actualizadas'],
                    $resultado['errores'],
                    $errores,
                    $resultado['duracion']
                ]
            );
        } catch (Exception $e) {
            logMessage("Error al registrar log de sincronización: " . $e->getMessage(), 'WARNING');
        }
    }
}