/**
 * TESA Syllabus Monitor
 * JavaScript para Gestión de Usuarios
 * 
 * @package TESASyllabusMonitor
 * @author Sistema TESA
 * @version 1.0
 */

// ============================================
// FUNCIONES AUXILIARES (DEBEN IR PRIMERO)
// ============================================

function mostrarCargando(selector) {
    $(selector).show();
}

function ocultarCargando(selector) {
    $(selector).hide();
}

function ocultarElemento(selector) {
    $(selector).hide();
}

function mostrarCargandoGlobal() {
    $('.loading-overlay').fadeIn(200);
}

function ocultarCargandoGlobal() {
    $('.loading-overlay').fadeOut(200);
}

function mostrarExito(mensaje) {
    mostrarAlerta(mensaje, 'success');
}

function mostrarError(mensaje) {
    mostrarAlerta(mensaje, 'danger');
}

function mostrarAlerta(mensaje, tipo) {
    const iconos = {
        success: '✅',
        danger: '❌',
        warning: '⚠️',
        info: 'ℹ️'
    };
    
    const alerta = `
        <div class="alert alert-${tipo} alert-dismissible">
            <strong>${iconos[tipo] || ''}</strong> ${mensaje}
            <button class="alert-close" onclick="this.parentElement.remove()">×</button>
        </div>
    `;
    
    $('#alerts-container').append(alerta);
    
    // Auto-hide después de 5 segundos
    setTimeout(() => {
        $('#alerts-container .alert').first().fadeOut(300, function() {
            $(this).remove();
        });
    }, 5000);
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

function formatearFecha(fechaStr) {
    if (!fechaStr) return '-';
    
    const fecha = new Date(fechaStr);
    const ahora = new Date();
    const diff = ahora - fecha;
    
    const minutos = Math.floor(diff / 60000);
    const horas = Math.floor(diff / 3600000);
    const dias = Math.floor(diff / 86400000);
    
    if (minutos < 1) return 'Hace un momento';
    if (minutos < 60) return `Hace ${minutos} min`;
    if (horas < 24) return `Hace ${horas} hora${horas !== 1 ? 's' : ''}`;
    if (dias < 7) return `Hace ${dias} día${dias !== 1 ? 's' : ''}`;
    
    return fecha.toLocaleDateString('es-EC', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function copiarTexto(elementId) {
    const elemento = document.getElementById(elementId);
    let texto = '';
    
    if (elemento.tagName === 'INPUT' || elemento.tagName === 'TEXTAREA') {
        texto = elemento.value;
    } else {
        texto = elemento.textContent || elemento.innerText;
    }
    
    copiarAlPortapapeles(texto);
}

function copiarAlPortapapeles(texto) {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(texto).then(() => {
            mostrarExito('📋 Copiado al portapapeles');
        });
    } else {
        // Fallback para navegadores antiguos
        const textarea = document.createElement('textarea');
        textarea.value = texto;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        mostrarExito('📋 Copiado al portapapeles');
    }
}

// ============================================
// INICIO DE LA APLICACIÓN
// ============================================

$(document).ready(function() {
    console.log('🚀 Módulo de Usuarios cargado');
    
    // Cargar usuarios al iniciar
    cargarUsuarios();
    
    // Event Listeners
    $('#btn-nuevo-usuario').click(abrirModalCrear);
    $('#btn-refresh').click(cargarUsuarios);
    $('#btn-toggle-log').click(toggleLog);
    $('#form-usuario').submit(guardarUsuario);
    $('#form-password').submit(cambiarPassword);
    $('#btn-copiar-correo').click(() => copiarTexto('correo-generado'));
    $('#btn-copiar-password').click(() => copiarTexto('password-generada'));
    $('#btn-regenerar-password').click(regenerarPassword);
});

/**
 * ============================================
 * CARGAR Y MOSTRAR USUARIOS
 * ============================================
 */

function cargarUsuarios() {
    console.log('📥 Cargando usuarios...');
    
    mostrarCargando('#loading-table');
    ocultarElemento('#empty-state');
    
    $.ajax({
        url: 'api/usuarios/listar_usuarios.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            console.log('✅ Usuarios cargados:', response);
            
            if (response.success) {
                if (response.data && response.data.length > 0) {
                    renderizarUsuarios(response.data);
                    $('#total-usuarios').text(`${response.data.length} usuario${response.data.length !== 1 ? 's' : ''}`);
                } else {
                    mostrarEstadoVacio();
                }
            } else {
                mostrarError('Error al cargar usuarios: ' + response.message);
            }
            
            ocultarCargando('#loading-table');
        },
        error: function(xhr, status, error) {
            console.error('❌ Error AJAX:', error);
            ocultarCargando('#loading-table');
            mostrarError('Error de conexión al cargar usuarios');
        }
    });
}

function renderizarUsuarios(usuarios) {
    const tbody = $('#tabla-usuarios tbody');
    tbody.empty();
    
    usuarios.forEach(usuario => {
        // 🔒 PROTECCIÓN: Verificar si es super admin Y si NO soy yo
        const esSuperAdmin = (usuario.id === 1 || usuario.es_super_admin === true);
        const soyYo = (usuario.id === USUARIO_ACTUAL_ID);
        const mostrarProteccion = esSuperAdmin && !soyYo; // Proteger solo si NO soy yo
        
        const estadoBadge = usuario.activo == 1 
            ? '<span class="badge badge-success">✅ Activo</span>'
            : '<span class="badge badge-danger">⏸️ Suspendido</span>';
        
        const rolBadge = usuario.rol === 'ADMIN'
            ? '<span class="badge badge-warning">👨‍💼 Admin</span>'
            : '<span class="badge badge-info">👤 Usuario</span>';
        
        const ultimoAcceso = usuario.ultimo_acceso 
            ? formatearFecha(usuario.ultimo_acceso)
            : '<span style="color: #95a5a6;">Nunca</span>';
        
        const row = `
            <tr data-id="${usuario.id}" ${soyYo ? 'style="background-color: #fff9e6;"' : ''}>
                <td>${usuario.id}</td>
                <td>
                    <strong>${escapeHtml(usuario.nombre_completo)}</strong>
                    ${soyYo ? '<span class="badge" style="background: #3498db; margin-left: 8px;">TÚ</span>' : ''}
                </td>
                <td>
                    <code>${escapeHtml(usuario.correo)}</code>
                </td>
                <td>${rolBadge}</td>
                <td>${estadoBadge}</td>
                <td>${ultimoAcceso}</td>
                <td>
                    ${mostrarProteccion ? `
                        <div class="super-admin-badge" title="Usuario principal del sistema - Protegido">
                            🔒 <span style="color: #fff;">Protegido</span>
                        </div>
                    ` : `
                        <div class="btn-group">
                            <button onclick="verPassword(${usuario.id})" 
                                    class="btn-icon" 
                                    title="Ver contraseña">
                                👁️
                            </button>
                            <button onclick="abrirModalPassword(${usuario.id}, '${escapeHtml(usuario.nombre_completo)}')" 
                                    class="btn-icon" 
                                    title="Cambiar contraseña">
                                🔒
                            </button>
                            ${soyYo ? '' : `
                                <button onclick="toggleEstado(${usuario.id}, ${usuario.activo})" 
                                        class="btn-icon ${usuario.activo == 1 ? 'btn-warning' : 'btn-success'}" 
                                        title="${usuario.activo == 1 ? 'Suspender' : 'Activar'}">
                                    ${usuario.activo == 1 ? '⏸️' : '✅'}
                                </button>
                                <button onclick="eliminarUsuario(${usuario.id}, '${escapeHtml(usuario.nombre_completo)}')" 
                                        class="btn-icon btn-danger" 
                                        title="Eliminar permanentemente">
                                    🗑️
                                </button>
                            `}
                        </div>
                    `}
                </td>
            </tr>
        `;
        
        tbody.append(row);
    });
}

function mostrarEstadoVacio() {
    $('#tabla-usuarios tbody').html(`
        <tr>
            <td colspan="7" style="text-align: center; padding: 40px;">
                <div class="empty-state-icon">👤</div>
                <p style="color: #95a5a6; margin-top: 10px;">No hay usuarios registrados</p>
            </td>
        </tr>
    `);
    $('#total-usuarios').text('0 usuarios');
}

/**
 * ============================================
 * CREAR/EDITAR USUARIO
 * ============================================
 */

function abrirModalCrear() {
    console.log('➕ Abriendo modal para crear usuario');
    
    // Resetear formulario
    $('#form-usuario')[0].reset();
    $('#usuario-id').val('');
    $('#modal-action').val('crear');
    $('#modal-title').text('➕ Crear Nuevo Usuario');
    $('#btn-guardar-usuario').text('✅ Guardar Usuario');
    
    // Resetear preview
    $('#correo-generado').text('-');
    $('#password-section').hide();
    
    // Mostrar modal
    $('#modal-usuario').fadeIn(300);
}

function cerrarModal() {
    $('#modal-usuario').fadeOut(300);
}

function generarCorreoPreview() {
    const nombreCompleto = $('#nombre-completo').val().trim();
    
    if (!nombreCompleto) {
        $('#correo-generado').text('-');
        return;
    }
    
    const correo = generarCorreoDesdeNombre(nombreCompleto);
    $('#correo-generado').text(correo);
}

function generarCorreoDesdeNombre(nombreCompleto) {
    // Limpiar y separar el nombre
    const palabras = nombreCompleto.trim().toLowerCase()
        .normalize("NFD").replace(/[\u0300-\u036f]/g, "") // Quitar tildes
        .split(/\s+/)
        .filter(p => p.length > 0);
    
    if (palabras.length === 0) return '';
    
    // Tomar primera letra del primer nombre + primer apellido completo
    const primeraLetra = palabras[0].charAt(0);
    const apellido = palabras[1] || palabras[0];
    
    const usuario = primeraLetra + apellido;
    return usuario + '@tesa.edu.ec';
}

function generarPassword() {
    const caracteres = {
        mayusculas: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
        minusculas: 'abcdefghijklmnopqrstuvwxyz',
        numeros: '0123456789',
        especiales: '#@$!'
    };
    
    let password = '';
    
    // Asegurar al menos uno de cada tipo
    password += caracteres.mayusculas.charAt(Math.floor(Math.random() * caracteres.mayusculas.length));
    password += caracteres.minusculas.charAt(Math.floor(Math.random() * caracteres.minusculas.length));
    password += caracteres.numeros.charAt(Math.floor(Math.random() * caracteres.numeros.length));
    password += caracteres.especiales.charAt(Math.floor(Math.random() * caracteres.especiales.length));
    
    // Completar hasta 12 caracteres
    const todos = caracteres.mayusculas + caracteres.minusculas + caracteres.numeros + caracteres.especiales;
    for (let i = 4; i < 12; i++) {
        password += todos.charAt(Math.floor(Math.random() * todos.length));
    }
    
    // Mezclar caracteres
    return password.split('').sort(() => Math.random() - 0.5).join('');
}

function generarPasswordAutomatica() {
    const nuevaPassword = generarPassword();
    $('#nueva-password-input').val(nuevaPassword);
}

function regenerarPassword() {
    const nuevaPassword = generarPassword();
    $('#password-generada').val(nuevaPassword);
}

function guardarUsuario(e) {
    e.preventDefault();
    
    const nombreCompleto = $('#nombre-completo').val().trim();
    const rol = $('#rol').val();
    const correo = $('#correo-input').val().trim();
    const password = generarPassword();
    
    if (!correo) {
        mostrarError('El correo es requerido.');
        return;
    }
    
    $('#password-generada').val(password);
    $('#password-section').slideDown(300);
    
    const $btnGuardar = $('#btn-guardar-usuario');
    $btnGuardar.prop('disabled', true).text('⏳ Guardando...');
    
    mostrarCargandoGlobal();
    
    $.ajax({
        url: 'api/usuarios/crear_usuario.php',
        method: 'POST',
        dataType: 'json',
        data: {
            nombre_completo: nombreCompleto,
            correo: correo,
            password: password,
            rol: rol
        },
        success: function(response) {
            ocultarCargandoGlobal();
            
            if (response.success) {
                mostrarExito(`Usuario creado exitosamente. Correo: ${correo}`);
                setTimeout(() => {
                    cerrarModal();
                    cargarUsuarios();
                }, 3000);
            } else {
                mostrarError('Error: ' + response.message);
                $btnGuardar.prop('disabled', false).text('✅ Guardar Usuario');
            }
        },
        error: function(xhr, status, error) {
            ocultarCargandoGlobal();
            mostrarError('Error de conexión al crear usuario');
            $btnGuardar.prop('disabled', false).text('✅ Guardar Usuario');
        }
    });
}

/**
 * ============================================
 * VER CONTRASEÑA
 * ============================================
 */

function verPassword(usuarioId) {
    console.log('👁️ Ver contraseña del usuario:', usuarioId);
    
    // 🔒 PROTECCIÓN: No mostrar contraseña del super admin SI NO SOY YO
    if (usuarioId === 1 && usuarioId !== USUARIO_ACTUAL_ID) {
        showCustomAlert('⛔ No tienes permisos para ver la contraseña del usuario principal del sistema', 'error');
        return;
    }
    
    mostrarCargandoGlobal();
    
    $.ajax({
        url: 'api/usuarios/listar_usuarios.php',
        method: 'GET',
        dataType: 'json',
        data: { id: usuarioId },
        success: function(response) {
            ocultarCargandoGlobal();
            
            if (response.success && response.data.length > 0) {
                const usuario = response.data[0];
                const password = usuario.password_visible || 'No disponible';
                
                showCustomConfirm({
                    title: 'Ver Contraseña',
                    message: `Usuario: ${usuario.nombre_completo}\nCorreo: ${usuario.correo}`,
                    icon: '🔑',
                    type: 'info',
                    showPassword: true,
                    password: password,
                    showCopy: true,
                    showCancel: false,
                    confirmText: 'Cerrar'
                });
            } else {
                showCustomAlert('No se pudo obtener la contraseña', 'error');
            }
        },
        error: function() {
            ocultarCargandoGlobal();
            showCustomAlert('Error al obtener la contraseña', 'error');
        }
    });
}

/**
 * ============================================
 * CAMBIAR CONTRASEÑA
 * ============================================
 */

function abrirModalPassword(usuarioId, nombreUsuario) {
    console.log('🔒 Cambiar contraseña:', usuarioId);
    
    // 🔒 PROTECCIÓN: No cambiar contraseña del super admin SI NO SOY YO
    if (usuarioId === 1 && usuarioId !== USUARIO_ACTUAL_ID) {
        showCustomAlert('⛔ No tienes permisos para cambiar la contraseña del usuario principal del sistema', 'error');
        return;
    }
    
    $('#password-usuario-id').val(usuarioId);
    $('#password-usuario-nombre').text(nombreUsuario);
    
    // Limpiar el campo de contraseña
    $('#nueva-password-input').val('');
    
    $('#modal-password').fadeIn(300);
}

function cerrarModalPassword() {
    $('#modal-password').fadeOut(300);
}

function cambiarPassword(e) {
    e.preventDefault();
    
    const usuarioId = $('#password-usuario-id').val();
    const nuevaPassword = $('#nueva-password-input').val().trim();
    
    // Validación
    if (nuevaPassword.length < 8) {
        mostrarError('La contraseña debe tener al menos 8 caracteres');
        return;
    }
    
    console.log('🔒 Cambiando contraseña del usuario:', usuarioId);
    console.log('Nueva contraseña:', nuevaPassword);
    
    mostrarCargandoGlobal();
    
    $.ajax({
        url: 'api/usuarios/actualizar_usuario.php',
        method: 'POST',
        dataType: 'json',
        data: {
            id: usuarioId,
            accion: 'cambiar_password',
            nueva_password: nuevaPassword
        },
        success: function(response) {
            console.log('Respuesta del servidor:', response);
            ocultarCargandoGlobal();
            
            if (response.success) {
                mostrarExito('✅ Contraseña actualizada correctamente. Nueva contraseña: ' + nuevaPassword);
                cerrarModalPassword();
                cargarUsuarios();
            } else {
                mostrarError('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error completo:', xhr.responseText);
            ocultarCargandoGlobal();
            mostrarError('Error al cambiar la contraseña');
        }
    });
}

/**
 * ============================================
 * ACTIVAR/SUSPENDER
 * ============================================
 */

async function toggleEstado(usuarioId, estadoActual) {
    // 🔒 PROTECCIÓN: No cambiar estado del super admin SI NO SOY YO
    if (usuarioId === 1 && usuarioId !== USUARIO_ACTUAL_ID) {
        showCustomAlert('⛔ No tienes permisos para modificar el estado del usuario principal del sistema', 'error');
        return;
    }
    
    const accion = estadoActual == 1 ? 'suspender' : 'activar';
    const mensaje = estadoActual == 1 
        ? '¿Suspender este usuario?\n\nNo podrá iniciar sesión hasta que sea reactivado.'
        : '¿Activar este usuario?\n\nPodrá iniciar sesión nuevamente.';
    
    const confirmado = await showCustomConfirm({
        title: estadoActual == 1 ? 'Suspender Usuario' : 'Activar Usuario',
        message: mensaje,
        icon: estadoActual == 1 ? '⏸️' : '✅',
        type: estadoActual == 1 ? 'warning' : 'success',
        confirmText: estadoActual == 1 ? 'Suspender' : 'Activar',
        danger: estadoActual == 1
    });
    
    if (!confirmado) return;
    
    console.log(`${accion} usuario:`, usuarioId);
    
    mostrarCargandoGlobal();
    
    $.ajax({
        url: 'api/usuarios/actualizar_usuario.php',
        method: 'POST',
        dataType: 'json',
        data: {
            id: usuarioId,
            accion: accion
        },
        success: function(response) {
            ocultarCargandoGlobal();
            
            if (response.success) {
                mostrarExito(response.message);
                cargarUsuarios();
            } else {
                showCustomAlert('Error: ' + response.message, 'error');
            }
        },
        error: function() {
            ocultarCargandoGlobal();
            showCustomAlert('Error al cambiar el estado del usuario', 'error');
        }
    });
}

/**
 * ============================================
 * ELIMINAR USUARIO
 * ============================================
 */

async function eliminarUsuario(usuarioId, nombreUsuario) {
    // 🔒 PROTECCIÓN: No eliminar al super admin SI NO SOY YO
    if (usuarioId === 1 && usuarioId !== USUARIO_ACTUAL_ID) {
        showCustomAlert('⛔ No tienes permisos para eliminar al usuario principal del sistema', 'error');
        return;
    }
    
    // Primera confirmación
    const confirmado1 = await showCustomConfirm({
        title: '⚠️ Eliminar Permanentemente',
        message: `Usuario: ${nombreUsuario}\n\nEsta acción NO se puede deshacer.\nSe eliminará toda la información del usuario.\n\n¿Estás seguro de continuar?`,
        icon: '🗑️',
        type: 'danger',
        confirmText: 'Sí, continuar',
        danger: true
    });
    
    if (!confirmado1) return;
    
    // Segunda confirmación
    const confirmado2 = await showCustomConfirm({
        title: '⚠️ Última Confirmación',
        message: `¿REALMENTE deseas eliminar a:\n\n${nombreUsuario}?\n\nEsta es tu última oportunidad para cancelar.`,
        icon: '⚠️',
        type: 'danger',
        confirmText: 'Eliminar definitivamente',
        cancelText: 'No, cancelar',
        danger: true
    });
    
    if (!confirmado2) return;
    
    console.log('🗑️ Eliminando usuario:', usuarioId);
    
    mostrarCargandoGlobal();
    
    $.ajax({
        url: 'api/usuarios/eliminar_usuario.php',
        method: 'POST',
        dataType: 'json',
        data: { id: usuarioId },
        success: function(response) {
            ocultarCargandoGlobal();
            
            if (response.success) {
                showCustomAlert('Usuario eliminado permanentemente', 'success');
                cargarUsuarios();
            } else {
                showCustomAlert('Error: ' + response.message, 'error');
            }
        },
        error: function() {
            ocultarCargandoGlobal();
            showCustomAlert('Error al eliminar el usuario', 'error');
        }
    });
}

/**
 * ============================================
 * LOG DE ACCIONES
 * ============================================
 */

function toggleLog() {
    const $logContainer = $('#log-container');
    
    if ($logContainer.is(':visible')) {
        $logContainer.slideUp(300);
    } else {
        $logContainer.slideDown(300);
        cargarLog();
    }
}

function cargarLog() {
    console.log('📜 Cargando log de acciones...');
    
    $.ajax({
        url: 'api/usuarios/obtener_log.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data) {
                renderizarLog(response.data);
            }
        },
        error: function() {
            console.error('Error al cargar el log');
        }
    });
}

function renderizarLog(logs) {
    const tbody = $('#tabla-log tbody');
    tbody.empty();
    
    if (logs.length === 0) {
        tbody.html(`
            <tr>
                <td colspan="5" style="text-align: center; padding: 20px; color: #95a5a6;">
                    No hay acciones registradas
                </td>
            </tr>
        `);
        return;
    }
    
    logs.forEach(log => {
        const accionBadge = getBadgeAccion(log.accion_realizada);
        
        const row = `
            <tr>
                <td>${formatearFecha(log.fecha_accion)}</td>
                <td><code>${escapeHtml(log.usuario_afectado_correo)}</code></td>
                <td>${accionBadge}</td>
                <td>${log.realizado_por_correo ? '<code>' + escapeHtml(log.realizado_por_correo) + '</code>' : '<span style="color: #95a5a6;">Sistema</span>'}</td>
                <td>${escapeHtml(log.detalles || '-')}</td>
            </tr>
        `;
        
        tbody.append(row);
    });
}

function getBadgeAccion(accion) {
    const badges = {
        'CREACION': '<span class="badge badge-success">➕ Creación</span>',
        'ACTIVACION': '<span class="badge badge-success">✅ Activación</span>',
        'DESACTIVACION': '<span class="badge badge-warning">⏸️ Desactivación</span>',
        'ELIMINACION': '<span class="badge badge-danger">🗑️ Eliminación</span>',
        'CAMBIO_PASSWORD': '<span class="badge badge-info">🔒 Cambio Password</span>',
        'CAMBIO_ROL': '<span class="badge badge-purple">🎭 Cambio Rol</span>'
    };
    
    return badges[accion] || accion;
}

/**
 * ============================================
 * MODALES DE CONFIRMACIÓN PERSONALIZADOS
 * ============================================
 */

function showCustomConfirm(options) {
    return new Promise((resolve) => {
        // Crear overlay
        const overlay = document.createElement('div');
        overlay.className = 'custom-confirm-overlay';
        
        // Crear modal
        const modal = document.createElement('div');
        modal.className = `custom-confirm-box ${options.type || 'info'}`;
        
        // HTML del modal
        modal.innerHTML = `
            <div class="custom-confirm-header">
                <div class="custom-confirm-icon">${options.icon || '❓'}</div>
                <h3 class="custom-confirm-title">${options.title || 'Confirmación'}</h3>
            </div>
            <div class="custom-confirm-body">
                <p class="custom-confirm-message">${options.message}</p>
                ${options.showPassword ? `
                    <div class="custom-confirm-password-display">
                        <div class="custom-confirm-password-label">Contraseña:</div>
                        <div class="custom-confirm-password-value" id="password-to-copy">${options.password}</div>
                    </div>
                ` : ''}
            </div>
            <div class="custom-confirm-footer">
                ${options.showCancel !== false ? `
                    <button class="custom-confirm-btn custom-confirm-btn-cancel" onclick="closeCustomConfirm(false)">
                        ❌ ${options.cancelText || 'Cancelar'}
                    </button>
                ` : ''}
                ${options.showCopy ? `
                    <button class="custom-confirm-btn custom-confirm-btn-copy" onclick="copyPasswordFromModal()">
                        📋 Copiar
                    </button>
                ` : ''}
                <button class="custom-confirm-btn ${options.danger ? 'custom-confirm-btn-danger' : 'custom-confirm-btn-confirm'}" onclick="closeCustomConfirm(true)">
                    ✅ ${options.confirmText || 'Aceptar'}
                </button>
            </div>
        `;
        
        // Agregar al DOM
        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        
        // Mostrar
        setTimeout(() => {
            overlay.style.display = 'block';
        }, 10);
        
        // Funciones de cierre
        window.closeCustomConfirm = (result) => {
            overlay.style.opacity = '0';
            setTimeout(() => {
                overlay.remove();
                delete window.closeCustomConfirm;
                delete window.copyPasswordFromModal;
                resolve(result);
            }, 200);
        };
        
        window.copyPasswordFromModal = () => {
            const passwordText = document.getElementById('password-to-copy').textContent;
            copiarAlPortapapeles(passwordText);
            mostrarExito('📋 Contraseña copiada al portapapeles');
        };
        
        // Cerrar con ESC
        const handleEsc = (e) => {
            if (e.key === 'Escape') {
                closeCustomConfirm(false);
                document.removeEventListener('keydown', handleEsc);
            }
        };
        document.addEventListener('keydown', handleEsc);
    });
}

// Función auxiliar para alertas simples
function showCustomAlert(message, type = 'info') {
    const icons = {
        success: '✅',
        error: '❌',
        warning: '⚠️',
        info: 'ℹ️'
    };
    
    return showCustomConfirm({
        title: type.charAt(0).toUpperCase() + type.slice(1),
        message: message,
        icon: icons[type],
        type: type,
        showCancel: false,
        confirmText: 'Entendido'
    });
}