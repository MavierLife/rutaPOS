// JavaScript para la funcionalidad de exportación de pedidos

// Variables globales
let estadisticasCargadas = false;
let exportacionEnProceso = false;

// Inicializar la página
document.addEventListener('DOMContentLoaded', function() {
    console.log('Página de exportación cargada');
    cargarEstadisticas();
    configurarFechasDefault();
});

// Configurar fechas por defecto
function configurarFechasDefault() {
    // Ya no necesitamos configurar fechas porque eliminamos esa funcionalidad
    console.log('Configuración de fechas omitida - funcionalidad removida');
}

// Cargar estadísticas de pedidos
async function cargarEstadisticas() {
    if (estadisticasCargadas) return;
    
    mostrarLoading(true);
    
    try {
        const response = await fetch('php/api/exportar.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'obtener_estadisticas'
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            document.getElementById('pedidosFinalizados').textContent = result.data.pedidos_finalizados || '0';
            document.getElementById('ultimaExportacion').textContent = result.data.ultima_exportacion || 'Nunca';
            estadisticasCargadas = true;
        } else {
            console.error('Error al cargar estadísticas:', result.message);
            mostrarError('Error al cargar las estadísticas');
        }
        
    } catch (error) {
        console.error('Error de conexión:', error);
        mostrarError('Error de conexión al cargar estadísticas');
    } finally {
        mostrarLoading(false);
    }
}

// Función para exportar pedidos
async function exportarPedidos() {
    console.log('=== INICIO EXPORTACIÓN ===');
    
    if (exportacionEnProceso) {
        console.log('Exportación ya en proceso, ignorando click');
        return;
    }
    
    exportacionEnProceso = true;
    console.log('Exportación marcada como en proceso');
    
    // Deshabilitar botones
    toggleBotones(true);
    
    // Mostrar progreso
    console.log('Mostrando progreso...');
    mostrarProgreso(true);
    
    try {
        // Preparar datos para enviar
        const datos = {
            action: 'exportar_pedidos'
        };
        console.log('Datos preparados:', datos);
        
        // Actualizar progreso
        actualizarProgreso(10, 'Consultando pedidos finalizados...');
        console.log('Progreso actualizado: 10%');
        
        console.log('Enviando petición a API...');
        const response = await fetch('php/api/exportar.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(datos)
        });
        
        console.log('Respuesta recibida:', response.status, response.statusText);
        
        if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status} - ${response.statusText}`);
        }
        
        actualizarProgreso(50, 'Generando archivo de exportación...');
        console.log('Progreso actualizado: 50%');
        
        console.log('Parseando respuesta JSON...');
        const result = await response.json();
        console.log('Resultado parseado:', result);
        
        if (result.success) {
            console.log('Exportación exitosa');
            actualizarProgreso(90, 'Finalizando exportación...');
            
            // Simular un pequeño delay para mostrar el progreso
            await new Promise(resolve => setTimeout(resolve, 500));
            
            actualizarProgreso(100, 'Exportación completada');
            console.log('Progreso completado: 100%');
            
            // Mostrar resultados
            setTimeout(() => {
                console.log('Mostrando resultados...');
                mostrarResultados(result.data);
                mostrarProgreso(false);
            }, 1000);
            
        } else {
            console.error('Error en resultado:', result.message);
            throw new Error(result.message || 'Error desconocido en la exportación');
        }
        
    } catch (error) {
        console.error('=== ERROR EN EXPORTACIÓN ===');
        console.error('Tipo de error:', error.constructor.name);
        console.error('Mensaje:', error.message);
        console.error('Stack:', error.stack);
        
        mostrarError('Error al exportar pedidos: ' + error.message);
        mostrarProgreso(false);
    } finally {
        console.log('Finalizando exportación...');
        exportacionEnProceso = false;
        toggleBotones(false);
        console.log('=== FIN EXPORTACIÓN ===');
    }
}

// Mostrar/ocultar loading overlay
function mostrarLoading(mostrar) {
    const overlay = document.getElementById('loadingOverlay');
    overlay.style.display = mostrar ? 'flex' : 'none';
}

// Mostrar/ocultar progreso
function mostrarProgreso(mostrar) {
    const card = document.getElementById('progressCard');
    card.style.display = mostrar ? 'block' : 'none';
    
    if (!mostrar) {
        // Reset progreso
        actualizarProgreso(0, 'Iniciando exportación...');
    }
}

// Actualizar barra de progreso
function actualizarProgreso(porcentaje, texto) {
    const fill = document.getElementById('progressFill');
    const textElement = document.getElementById('progressText');
    
    fill.style.width = porcentaje + '%';
    textElement.textContent = texto;
}

// Mostrar resultados de exportación
function mostrarResultados(datos) {
    console.log('=== INICIO mostrarResultados ===');
    console.log('Datos recibidos:', datos);
    
    const card = document.getElementById('resultsCard');
    console.log('Elemento resultsCard encontrado:', card ? 'SÍ' : 'NO');
    
    if (!card) {
        console.error('ERROR: No se encontró el elemento resultsCard');
        mostrarError('Error: No se encontró la tarjeta de resultados en el DOM');
        return;
    }
    
    // Verificar y actualizar cada elemento
    const nombreArchivoEl = document.getElementById('nombreArchivo');
    console.log('Elemento nombreArchivo encontrado:', nombreArchivoEl ? 'SÍ' : 'NO');
    if (nombreArchivoEl) {
        nombreArchivoEl.textContent = datos.nombre_archivo || 'pedidos_export.txt';
        console.log('Nombre archivo actualizado:', nombreArchivoEl.textContent);
    }
    
    const totalRegistrosEl = document.getElementById('totalRegistros');
    console.log('Elemento totalRegistros encontrado:', totalRegistrosEl ? 'SÍ' : 'NO');
    if (totalRegistrosEl) {
        totalRegistrosEl.textContent = datos.total_registros || '0';
        console.log('Total registros actualizado:', totalRegistrosEl.textContent);
    }
    
    const tamanoArchivoEl = document.getElementById('tamanoArchivo');
    console.log('Elemento tamanoArchivo encontrado:', tamanoArchivoEl ? 'SÍ' : 'NO');
    if (tamanoArchivoEl) {
        tamanoArchivoEl.textContent = datos.tamano_archivo || '0 KB';
        console.log('Tamaño archivo actualizado:', tamanoArchivoEl.textContent);
    }
    
    // Configurar botón de descarga
    const btnDescargar = document.getElementById('btnDescargar');
    console.log('Elemento btnDescargar encontrado:', btnDescargar ? 'SÍ' : 'NO');
    if (btnDescargar) {
        btnDescargar.onclick = function() {
            console.log('Botón descarga clickeado, archivo:', datos.ruta_archivo || datos.nombre_archivo);
            descargarArchivo(datos.ruta_archivo || datos.nombre_archivo);
        };
        console.log('Evento click configurado en botón descarga');
    }
    
    // Mostrar la tarjeta
    console.log('Mostrando tarjeta de resultados...');
    card.style.display = 'block';
    console.log('Tarjeta display cambiado a:', card.style.display);
    console.log('Tarjeta visible:', card.offsetHeight > 0 ? 'SÍ' : 'NO');
    
    // Actualizar estadísticas
    estadisticasCargadas = false;
    cargarEstadisticas();
    
    console.log('=== FIN mostrarResultados ===');
}

// Ocultar resultados
function ocultarResultados() {
    const card = document.getElementById('resultsCard');
    card.style.display = 'none';
}

// Descargar archivo
function descargarArchivo(nombreArchivo) {
    const link = document.createElement('a');
    link.href = 'php/api/exportar.php?action=descargar&archivo=' + encodeURIComponent(nombreArchivo);
    link.download = nombreArchivo;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Reset exportación
function resetExportacion() {
    ocultarResultados();
    mostrarProgreso(false);
    exportacionEnProceso = false;
    
    // Recargar estadísticas
    estadisticasCargadas = false;
    cargarEstadisticas();
}

// Mostrar mensajes de error
function mostrarError(mensaje) {
    // Crear modal de error simple
    const errorModal = document.createElement('div');
    errorModal.className = 'error-modal';
    errorModal.innerHTML = `
        <div class="error-content">
            <div class="error-header">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Error</h3>
            </div>
            <div class="error-body">
                <p>${mensaje}</p>
            </div>
            <div class="error-actions">
                <button class="btn-error-ok" onclick="cerrarError(this)">Entendido</button>
            </div>
        </div>
    `;
    
    // Agregar estilos inline para el modal de error
    errorModal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
    `;
    
    const errorContent = errorModal.querySelector('.error-content');
    errorContent.style.cssText = `
        background: white;
        border-radius: 10px;
        padding: 0;
        max-width: 400px;
        width: 90%;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        overflow: hidden;
    `;
    
    const errorHeader = errorModal.querySelector('.error-header');
    errorHeader.style.cssText = `
        background: #e74c3c;
        color: white;
        padding: 15px 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    `;
    
    const errorBody = errorModal.querySelector('.error-body');
    errorBody.style.cssText = `
        padding: 20px;
    `;
    
    const errorActions = errorModal.querySelector('.error-actions');
    errorActions.style.cssText = `
        padding: 0 20px 20px 20px;
        text-align: right;
    `;
    
    const btnOk = errorModal.querySelector('.btn-error-ok');
    btnOk.style.cssText = `
        background: #e74c3c;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 5px;
        cursor: pointer;
        font-weight: 600;
    `;
    
    document.body.appendChild(errorModal);
}

// Cerrar modal de error
function cerrarError(btn) {
    const modal = btn.closest('.error-modal');
    if (modal) {
        document.body.removeChild(modal);
    }
}

// Función para formatear fechas
function formatearFecha(fecha) {
    if (!fecha) return 'N/A';
    
    const date = new Date(fecha);
    return date.toLocaleDateString('es-ES', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Validar formulario antes de exportar
function validarFormulario(tipo) {
    if (tipo === 'rango') {
        const fechaInicio = document.getElementById('fechaInicio').value;
        const fechaFin = document.getElementById('fechaFin').value;
        
        if (!fechaInicio || !fechaFin) {
            return { valido: false, mensaje: 'Debe seleccionar ambas fechas' };
        }
        
        const inicio = new Date(fechaInicio);
        const fin = new Date(fechaFin);
        const hoy = new Date();
        
        if (inicio > fin) {
            return { valido: false, mensaje: 'La fecha de inicio no puede ser mayor que la fecha fin' };
        }
        
        if (inicio > hoy) {
            return { valido: false, mensaje: 'La fecha de inicio no puede ser futura' };
        }
        
        // Validar que no sea un rango muy grande (más de 1 año)
        const unAno = 365 * 24 * 60 * 60 * 1000;
        if ((fin - inicio) > unAno) {
            return { valido: false, mensaje: 'El rango de fechas no puede ser mayor a 1 año' };
        }
    }
    
    return { valido: true };
}

// Deshabilitar/habilitar botones durante exportación
function toggleBotones(deshabilitar) {
    console.log('Cambiando estado de botones:', deshabilitar ? 'deshabilitados' : 'habilitados');
    const botones = document.querySelectorAll('.btn-export');
    console.log('Botones encontrados:', botones.length);
    botones.forEach(btn => {
        btn.disabled = deshabilitar;
        if (deshabilitar) {
            btn.style.opacity = '0.6';
            btn.style.cursor = 'not-allowed';
        } else {
            btn.style.opacity = '1';
            btn.style.cursor = 'pointer';
        }
    });
}