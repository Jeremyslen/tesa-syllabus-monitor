/**
 * TESA Syllabus Monitor - JavaScript Principal
 * Manejo de interfaz y comunicación con API para monitoreo de syllabus
 * 
 * @author Sistema TESA
 * @version 1.1 - Con filtro de módulo
 */

(function($) {
    'use strict';

    // --- CONFIGURACIÓN GLOBAL ---
    const CONFIG = {
        apiBaseUrl: 'api/',
        loadingDelay: 300,
        animationSpeed: 300
    };

    // --- ESTADO GLOBAL ---
    let selectedPeriodo = null;
    let selectedCarrera = null;
    let selectedModulo = null;
    let todasLasClases = [];

    // --- MANEJO DE INTERFAZ ---

    function showLoading(message = 'Cargando...') {
        $('.loading-text').text(message);
        $('.loading-overlay').addClass('active');
    }

    function hideLoading() {
        $('.loading-overlay').removeClass('active');
    }

    function showAlert(message, type = 'info') {
        const alertClass = `alert-${type}`;
        const alertHtml = `
            <div class="alert ${alertClass}" role="alert">
                <span>${message}</span>
            </div>
        `;
        
        $('.alerts-container').html(alertHtml);
        
        setTimeout(() => {
            $('.alert').fadeOut(CONFIG.animationSpeed, function() {
                $(this).remove();
            });
        }, 5000);
    }

    function clearAlerts() {
        $('.alerts-container').empty();
    }

    // --- MANEJO DE FORMULARIOS ---

    function disableSelect(selector) {
        $(selector).prop('disabled', true).val('');
    }

    function enableSelect(selector) {
        $(selector).prop('disabled', false);
    }

    function clearSelect(selector, placeholder = 'Seleccione...') {
        $(selector).empty().append(`<option value="">${placeholder}</option>`);
    }

    function populateSelect(selector, data, valueKey, textKey, dataIdKey = null) {
        const $select = $(selector);
        clearSelect(selector);
        
        data.forEach(item => {
            const $option = $(`<option value="${item[valueKey]}">${item[textKey]}</option>`);
            if (dataIdKey && item[dataIdKey]) {
                $option.attr('data-id', item[dataIdKey]);
            }
            $select.append($option);
        });
    }

    // --- COMUNICACIÓN CON API ---

    function apiRequest(endpoint, method = 'GET', data = null, timeout = 300000) {
        const config = {
            url: CONFIG.apiBaseUrl + endpoint,
            method: method,
            dataType: 'json',
            timeout: timeout
        };

        if (data) {
            if (method === 'GET') {
                config.data = data;
            } else {
                config.contentType = 'application/json';
                config.data = JSON.stringify(data);
            }
        }

        return $.ajax(config);
    }

    function cargarPeriodos() {
        showLoading('Cargando períodos...');
        clearAlerts();

        apiRequest('get_periodos.php')
            .done(function(response) {
                if (response.success && response.data.length > 0) {
                    populateSelect('#periodo', response.data, 'org_unit_id', 'nombre', 'id');
                    enableSelect('#periodo');
                    showAlert(`${response.data.length} períodos cargados`, 'success');
                } else {
                    showAlert('No se encontraron períodos', 'warning');
                }
            })
            .fail(function(xhr) {
                console.error('Error al cargar períodos:', xhr);
                showAlert('Error al cargar períodos. Verifique la configuración del token.', 'danger');
            })
            .always(function() {
                hideLoading();
            });
    }

    function cargarCarreras(orgUnitId) {
        showLoading('Cargando carreras...');
        clearAlerts();
        
        // Limpiar dropdowns dependientes
        disableSelect('#carrera');
        disableSelect('#modulo');
        clearSelect('#carrera', 'Seleccione una carrera...');
        $('#modulo').val('');
        limpiarTabla();
        todasLasClases = [];

        apiRequest('get_carreras.php', 'GET', { org_unit_id: orgUnitId })
            .done(function(response) {
                if (response.success && response.data.length > 0) {
                    populateSelect('#carrera', response.data, 'codigo', 'nombre');
                    enableSelect('#carrera');
                    showAlert(`${response.data.length} carreras encontradas`, 'success');
                } else {
                    showAlert('No se encontraron carreras en este período', 'warning');
                }
            })
            .fail(function(xhr) {
                console.error('Error al cargar carreras:', xhr);
                showAlert('Error al cargar carreras', 'danger');
            })
            .always(function() {
                hideLoading();
            });
    }

    // --- BARRA DE PROGRESO ---

    function mostrarBarraProgreso(mensaje = 'Procesando...') {
        const progressHtml = `
            <div class="progress-overlay">
                <div class="progress-container">
                    <h3>${mensaje}</h3>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill"></div>
                    </div>
                    <p class="progress-text">Esto puede tomar varios minutos en la primera carga...</p>
                    <p class="progress-time">Tiempo transcurrido: <span id="elapsed-time">0s</span></p>
                </div>
            </div>
        `;
        
        $('body').append(progressHtml);
        
        let progress = 0;
        const progressInterval = setInterval(() => {
            progress += Math.random() * 15;
            if (progress > 90) progress = 90;
            $('.progress-bar-fill').css('width', progress + '%');
        }, 500);
        
        const startTime = Date.now();
        const timeInterval = setInterval(() => {
            const elapsed = ((Date.now() - startTime) / 1000).toFixed(0);
            $('#elapsed-time').text(elapsed + 's');
        }, 1000);
        
        $('.progress-overlay').data('intervals', { progressInterval, timeInterval });
    }

    function ocultarBarraProgreso() {
        const intervals = $('.progress-overlay').data('intervals');
        if (intervals) {
            clearInterval(intervals.progressInterval);
            clearInterval(intervals.timeInterval);
        }
        
        $('.progress-bar-fill').css('width', '100%');
        
        setTimeout(() => {
            $('.progress-overlay').fadeOut(300, function() {
                $(this).remove();
            });
        }, 500);
    }

    // --- GESTIÓN DE CLASES ---

    function cargarClases(periodoId, orgUnitId, carreraCodigo) {
        clearAlerts();
        mostrarBarraProgreso('Sincronizando clases desde Brightspace...');

        const params = {
            org_unit_id: orgUnitId,
            carrera_codigo: carreraCodigo,
            usar_cache: true
        };

        const startTime = Date.now();

        apiRequest('get_clases.php', 'GET', params, 600000)
            .done(function(response) {
                if (response.success) {
                    const clases = response.data.clases || [];
                    const sync = response.data.sincronizacion;
                    const duration = ((Date.now() - startTime) / 1000).toFixed(1);
                    
                    if (clases.length > 0) {
                        todasLasClases = clases;
                        enableSelect('#modulo');
                        aplicarFiltros();
                        
                        let mensaje = `${clases.length} clases cargadas`;
                        if (sync && sync.actualizadas > 0) {
                            mensaje += ` (${sync.actualizadas} actualizadas desde la API en ${duration}s)`;
                        } else if (sync && sync.desde_cache) {
                            mensaje += ` desde cache`;
                        }
                        showAlert(mensaje, 'success');
                    } else {
                        todasLasClases = [];
                        disableSelect('#modulo');
                        mostrarEstadoVacio('No se encontraron clases para los filtros seleccionados');
                        showAlert('No se encontraron clases', 'info');
                    }
                } else {
                    todasLasClases = [];
                    disableSelect('#modulo');
                    mostrarEstadoVacio('Error al cargar las clases');
                    showAlert(response.message || 'Error al cargar clases', 'danger');
                }
            })
            .fail(function(xhr) {
                console.error('Error al cargar clases:', xhr);
                const errorMsg = xhr.responseJSON?.message || 'Error de conexión al cargar clases';
                todasLasClases = [];
                disableSelect('#modulo');
                mostrarEstadoVacio(errorMsg);
                showAlert(errorMsg, 'danger');
            })
            .always(function() {
                ocultarBarraProgreso();
            });
    }

    // --- FILTRADO DE CLASES ---

    function extraerModulo(nombreCompleto) {
        const match = nombreCompleto.match(/\.[A-Z]{2}\.([A-C])\.\d{4}/);
        return match ? match[1] : null;
    }

    function aplicarFiltros() {
        const moduloSeleccionado = selectedModulo;
        
        if (!moduloSeleccionado) {
            mostrarClases(todasLasClases);
            return;
        }
        
        const clasesFiltradas = todasLasClases.filter(clase => {
            const modulo = extraerModulo(clase.nombre);
            return modulo === moduloSeleccionado;
        });
        
        if (clasesFiltradas.length > 0) {
            mostrarClases(clasesFiltradas);
            showAlert(`${clasesFiltradas.length} clases del Módulo ${moduloSeleccionado}`, 'info');
        } else {
            mostrarEstadoVacio(`No hay clases del Módulo ${moduloSeleccionado}`);
            showAlert(`No se encontraron clases del Módulo ${moduloSeleccionado}`, 'warning');
        }
    }

    // --- VISUALIZACIÓN DE DATOS ---

    function mostrarClases(clases) {
        const $tbody = $('#tabla-clases tbody');
        $tbody.empty();

        clases.forEach(clase => {
            const syllabusClass = clase.tiene_syllabus === 'SI' ? 'badge-success' : 'badge-danger';
            
            // Lógica de colores para calificación
            let calificacionColor;
            let calificacionIcon;

            if (clase.calificacion_final === 0) {
                calificacionColor = '#e74c3c';
                calificacionIcon = '❌';
            } else if (clase.calificacion_final === 85) {
                calificacionColor = '#27ae60';
                calificacionIcon = '✅';
            } else {
                calificacionColor = '#e67e22';
                calificacionIcon = '⚠️';
            }
            
            const row = `
                <tr>
                    <td class="col-nrc">${clase.nrc}</td>
                    <td class="col-nombre" title="${clase.nombre}">${clase.nombre_corto}</td>
                    <td class="col-syllabus">
                        <span class="badge ${syllabusClass}">${clase.tiene_syllabus}</span>
                    </td>
                    <td class="col-calificacion" style="color: ${calificacionColor}; font-weight: bold;">
                        ${calificacionIcon} ${clase.calificacion_final.toFixed(2)}
                    </td>
                    <td class="col-documentos">
                        <span class="badge badge-info">${clase.total_documentos}</span>
                    </td>
                </tr>
            `;
            
            $tbody.append(row);
        });

        $('.results-count').text(`${clases.length} clases`);
        $('.results-section').show();
        $('.empty-state-container').hide();
    }

    function mostrarEstadoVacio(mensaje = 'Seleccione un período y una carrera para ver las clases') {
        limpiarTabla();
        
        $('.empty-state-container').show();
        $('.empty-state p').text(mensaje);
        $('.results-section').hide();
    }

    function limpiarTabla() {
        $('#tabla-clases tbody').empty();
        $('.results-count').text('0 clases');
    }

    // --- ACTUALIZACIÓN DE CACHE ---

    function actualizarCache() {
        if (!selectedPeriodo) {
            showAlert('Debe seleccionar un período primero', 'warning');
            return;
        }

        if (!selectedCarrera) {
            showAlert('Debe seleccionar una carrera primero', 'warning');
            return;
        }

        mostrarBarraProgreso(`Actualizando clases de ${selectedCarrera}... (2-5 minutos)`);
        clearAlerts();

        const data = {
            periodo_org_unit_id: selectedPeriodo.org_unit_id,
            tipo: 'completo',
            carrera_codigo: selectedCarrera  // 🎯 Solo actualiza esta carrera
        };

        const startTime = Date.now();

        apiRequest('actualizar_cache.php', 'POST', data, 600000)
            .done(function(response) {
                const duration = ((Date.now() - startTime) / 1000).toFixed(1);
                
                ocultarBarraProgreso();
                
                if (response.success) {
                    const stats = response.data || {};
                    let mensaje = `✅ Cache actualizado para ${selectedCarrera}: ` +
                                `${stats.total || 0} clases procesadas ` +
                                `(${stats.actualizadas || 0} actualizadas, ${stats.nuevas || 0} nuevas) ` +
                                `en ${duration}s`;
                    
                    showAlert(mensaje, 'success');
                    
                    // Recargar las clases de esta carrera
                    setTimeout(() => {
                        cargarClases(
                            selectedPeriodo.id,
                            selectedPeriodo.org_unit_id,
                            selectedCarrera
                        );
                    }, 500);
                } else {
                    showAlert(response.message || 'Error al actualizar cache', 'danger');
                }
            })
            .fail(function(xhr) {
                console.error('Error al actualizar cache:', xhr);
                ocultarBarraProgreso();
                
                let errorMsg = 'Error de conexión al actualizar cache';
                
                if (xhr.status === 504 || xhr.status === 0) {
                    errorMsg = 'Tiempo de espera agotado. Intente con menos clases o reintente.';
                } else if (xhr.responseJSON?.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                
                showAlert(errorMsg, 'danger');
            });
    }

    // --- MANEJADORES DE EVENTOS ---

    $('#periodo').on('change', function() {
        const orgUnitId = $(this).val();
        
        if (orgUnitId) {
            const selectedOption = $(this).find('option:selected');
            const periodoNombre = selectedOption.text();
            
            selectedPeriodo = {
                id: null,
                org_unit_id: parseInt(orgUnitId),
                nombre: periodoNombre
            };
            
            cargarCarreras(orgUnitId);
        } else {
            selectedPeriodo = null;
            disableSelect('#carrera');
            disableSelect('#modulo');
            clearSelect('#carrera');
            $('#modulo').val('');
            mostrarEstadoVacio();
            todasLasClases = [];
        }
    });

    $('#carrera').on('change', function() {
        const carreraCodigo = $(this).val();
        
        if (carreraCodigo && selectedPeriodo) {
            selectedCarrera = carreraCodigo;
            selectedModulo = null;
            $('#modulo').val('');
            cargarClases(selectedPeriodo.id, selectedPeriodo.org_unit_id, carreraCodigo);
        } else {
            selectedCarrera = null;
            disableSelect('#modulo');
            $('#modulo').val('');
            mostrarEstadoVacio();
            todasLasClases = [];
        }
    });

    $('#modulo').on('change', function() {
        const modulo = $(this).val();
        selectedModulo = modulo || null;
        
        if (todasLasClases.length > 0) {
            aplicarFiltros();
        }
    });

    $('#btn-actualizar').on('click', function() {
        actualizarCache();
    });

    // --- INICIALIZACIÓN ---

    $(document).ready(function() {
        console.log('TESA Syllabus Monitor - Inicializado (v1.1 con filtro de módulo)');
        
        cargarPeriodos();
        mostrarEstadoVacio();
    });

})(jQuery);