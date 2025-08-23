// Datos del cliente desde PHP (se inicializan en el archivo PHP)
// clienteData y pedidoId se declaran en pedidos.php

let productos = [];
let totalPedido = 0;
let productosDisponibles = [];
let productosFiltrados = [];
let filtroActual = 'productos';
let productoSeleccionado = null;

// Variables para lógica de precios y unidades
let modoVenta = 'fardos'; // 'fardos' o 'unidades'
let tipoPrecionActual = 'especial';

// Variables para swipe y eliminación
let swipeStartX = 0;
let swipeStartY = 0;
let swipeStartTime = 0;
let productoAEliminar = null;
let swipeActive = false;

// Función helper para obtener unidades por fardo
function obtenerUnidadesPorFardo(producto) {
    return producto ? (producto.unidades || 1) : 1;
}

// Configurar tipo de pago
document.querySelectorAll('.btn-tipo-pago').forEach(btn => {
    btn.addEventListener('click', function() {
        // Validar si se puede seleccionar crédito
        if (this.dataset.tipo === 'credito' && !clienteData.creditoAutorizado) {
            alert('Este cliente no tiene crédito autorizado. Solo se permite pago de contado.');
            return;
        }
        
        cambiarTipoPago(this.dataset.tipo);
    });
});

// Configurar pestañas de productos
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        filtroActual = this.dataset.filter;
        cargarProductos();
    });
});

// Configurar navegación alfabética
document.querySelectorAll('.letter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const letra = this.dataset.letter;
        cargarProductos('', letra);
    });
});

// Búsqueda en tiempo real
document.getElementById('productosSearch').addEventListener('input', function() {
    const termino = this.value.trim();
    if (termino.length >= 2 || termino.length === 0) {
        cargarProductos(termino);
    }
});

function mostrarAgregarProducto() {
    document.getElementById('productosModal').style.display = 'flex';
    cargarProductos();
}

function cerrarModalProductos() {
    document.getElementById('productosModal').style.display = 'none';
}

function buscarProductos() {
    const termino = document.getElementById('productosSearch').value.trim();
    cargarProductos(termino);
}

async function cargarProductos(busqueda = '', letra = '') {
    try {
        const params = new URLSearchParams({
            filter: filtroActual
        });
        
        if (busqueda) params.append('search', busqueda);
        if (letra) params.append('letter', letra);
        
        const response = await fetch(`php/api/productos.php?${params}`);
        const data = await response.json();
        
        if (data.success) {
            productosDisponibles = data.productos;
            mostrarProductos(productosDisponibles);
            document.getElementById('productosCount').textContent = data.total;
        } else {
            document.getElementById('productosListModal').innerHTML = '<div class="error">Error: ' + data.message + '</div>';
        }
    } catch (error) {
        document.getElementById('productosListModal').innerHTML = '<div class="error">Error de conexión</div>';
    }
}

function mostrarProductos(productos) {
    const container = document.getElementById('productosListModal');
    
    if (productos.length === 0) {
        container.innerHTML = '<div class="no-results">No se encontraron productos</div>';
        return;
    }
    
    const html = productos.map(producto => {
        const stockActual = producto.existencia || 0;
        const unidadesPorFardo = producto.unidades || 1;
        
        // Determinar color y estado del stock
        let stockColor, stockTexto, stockIcon;
        if (stockActual <= 0) {
            stockColor = 'red';
            stockTexto = 'Sin stock';
            stockIcon = 'fas fa-times-circle';
        } else if (stockActual <= 10) {
            stockColor = 'orange';
            stockTexto = `Stock bajo: ${producto.stockDisplay || stockActual + 'U'}`;
            stockIcon = 'fas fa-exclamation-triangle';
        } else {
            stockColor = 'green';
            stockTexto = `Stock: ${producto.stockDisplay || stockActual + 'U'}`;
            stockIcon = 'fas fa-check-circle';
        }
        
        // Formato de precios: D: $1.95 M: $38.00 - $1.90
        const preciosDisplay = `D: $${producto.precioDetalleUnitario} M: $${producto.precioMayoreoFardo} - $${producto.precioMayoreoUnitario}`;
        
        // Deshabilitar botón si no hay stock
        const botonDeshabilitado = stockActual <= 0 ? 'disabled' : '';
        const claseBoton = stockActual <= 0 ? 'btn-add-producto-modal disabled' : 'btn-add-producto-modal';
        
        return `
            <div class="producto-item-modal">
                <button class="${claseBoton}" onclick="seleccionarProducto('${producto.CodigoProd}')" ${botonDeshabilitado}>
                    <i class="fas fa-plus"></i>
                </button>
                <div class="producto-info">
                    <div class="producto-nombre">${producto.descripcion}${producto.contenido1 ? ` - ${producto.contenido1}` : ''}</div>
                    <div class="producto-detalles">
                        <span class="producto-unidades">${producto.unidades}U/F</span>
                        <span class="producto-stock" style="color: ${stockColor}">
                            <i class="${stockIcon}"></i> ${stockTexto}
                        </span>
                        <span class="producto-precios">${preciosDisplay}</span>
                    </div>
                </div>
                <div class="producto-imagen">
                    <i class="fas fa-box"></i>
                </div>
            </div>
        `;
    }).join('');
    
    container.innerHTML = html;
}

function seleccionarProducto(codigoProducto) {
    console.log('=== INICIO seleccionarProducto ===');
    console.log('Código producto:', codigoProducto);
    
    const producto = productosDisponibles.find(p => p.CodigoProd === codigoProducto);
    console.log('Producto encontrado:', producto);
    
    if (!producto) {
        console.log('ERROR: Producto no encontrado');
        return;
    }
    
    productoSeleccionado = producto;
    console.log('Producto seleccionado asignado:', productoSeleccionado);
    
    // Cerrar modal de productos primero
    console.log('Cerrando modal de productos...');
    cerrarModalProductos();
    
    // Usar setTimeout para evitar conflictos entre modales
    setTimeout(() => {
        console.log('Iniciando configuración del modal agregar producto...');
        
        // Actualizar información del producto en el modal
        const tituloElement = document.getElementById('productoTitulo');
        
        console.log('Elemento productoTitulo:', tituloElement);
        if (tituloElement) {
            // Mostrar descripción y contenido dinámico en la línea azul
            const descripcion = producto.descripcion || 'PRODUCTO SELECCIONADO';
            const contenido = producto.contenido1 || '-';
            tituloElement.innerHTML = `<div>${descripcion}</div><div>${contenido}</div>`;
            console.log('Título actualizado con descripción y contenido');
        }
        
        // Actualizar stock
        const unidadesMinimasElement = document.getElementById('unidadesMinimas');
        const stockMinimoElement = document.getElementById('stockMinimo');
        
        if (unidadesMinimasElement) {
            unidadesMinimasElement.textContent = '1';
            console.log('Unidades mínimas actualizado: 1');
        }
        
        if (stockMinimoElement) {
            stockMinimoElement.textContent = producto.existencia || '0';
            console.log('Stock actualizado:', producto.existencia || '0');
        }
        
        // Resetear modo a fardos por defecto
        modoVenta = 'fardos';
        tipoPrecionActual = 'mayoreo';
        const btnModo = document.getElementById('btnModoUnidad');
        const modoTexto = document.getElementById('modoTexto');
        if (btnModo) {
            btnModo.textContent = 'UNIDAD'; // Texto fijo
            btnModo.classList.remove('unidades'); // Fondo blanco (OFF)
        }
        if (modoTexto) {
            modoTexto.textContent = 'Fardos';
        }
        
        // Activar visualmente el botón de mayoreo por defecto
        document.querySelectorAll('.precio-btn:not(#btnModoUnidad)').forEach(btn => {
            btn.classList.remove('precio-btn-active');
        });
        const btnMayoreo = document.querySelector('[data-tipo="mayoreo"]');
        if (btnMayoreo) {
            btnMayoreo.classList.add('precio-btn-active');
        }
        
        // Configurar precio inicial (mayoreo por defecto)
        console.log('Actualizando precio inicial...');
        actualizarPrecioSegunTipo('mayoreo');
        
        // Resetear valores
        const cantidadInput = document.getElementById('cantidadInput');
        
        if (cantidadInput) cantidadInput.value = '1';
        
        console.log('Valores reseteados');
        
        // Calcular total inicial
        console.log('Calculando total inicial...');
        calcularTotal();
        
        // Mostrar modal de agregar producto
        const modal = document.getElementById('agregarProductoModal');
        console.log('Modal element:', modal);
        
        if (modal) {
            console.log('Mostrando modal...');
            modal.classList.add('show');
            console.log('Modal classList:', modal.classList.toString());
            console.log('Modal computed style:', window.getComputedStyle(modal).display);
        } else {
            console.log('ERROR: Modal agregarProductoModal no encontrado');
        }
        
        console.log('=== FIN seleccionarProducto ===');
     }, 100);
 }

// Configurar event listeners para botones de precio
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.precio-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            // Si es el botón de modo unidad, hacer toggle
            if (this.id === 'btnModoUnidad') {
                toggleModoVenta();
                return;
            }
            
            const tipo = this.getAttribute('data-tipo');
            
            // Validar precio especial
            if (tipo === 'especial') {
                // Verificar si el cliente puede acceder al precio especial
                const clientePuedeEspecial = clienteData.venderprecioespecial == 1;
                if (!clientePuedeEspecial) {
                    alert('Este cliente no tiene autorización para precio especial.');
                    return;
                }
            }
            
            // Remover clase activa de todos los botones de precio (excepto modo)
            document.querySelectorAll('.precio-btn:not(#btnModoUnidad)').forEach(b => {
                b.classList.remove('precio-btn-active');
            });
            
            // Agregar clase activa al botón clickeado
            this.classList.add('precio-btn-active');
            
            // Actualizar precio
            tipoPrecionActual = tipo;
            actualizarPrecioSegunTipo(tipo);
        });
    });
    
    // Event listeners para inputs
    document.getElementById('cantidadInput').addEventListener('input', calcularTotal);
});

function toggleModoVenta() {
    const btnModo = document.getElementById('btnModoUnidad');
    const modoTexto = document.getElementById('modoTexto');
    const productoTitulo = document.getElementById('productoTitulo');
    
    // Mantener la cantidad actual sin recalcular
    const cantidadActual = parseInt(document.getElementById('cantidadInput').value) || 1;
    
    if (modoVenta === 'fardos') {
        // Cambiar a modo UNIDADES (ON)
        modoVenta = 'unidades';
        btnModo.textContent = 'UNIDAD'; // Texto fijo
        btnModo.classList.add('unidades'); // Fondo verde
        modoTexto.textContent = 'Unidades';
        
        // Actualizar título con descripción y contenido2 (unidades)
        if (productoSeleccionado && productoTitulo) {
            const descripcion = productoSeleccionado.descripcion || 'PRODUCTO SELECCIONADO';
            const contenido = productoSeleccionado.contenido2 || '-';
            productoTitulo.innerHTML = `<div>${descripcion}</div><div>${contenido}</div>`;
        }
        
        // Mantener la cantidad actual (no recalcular)
        document.getElementById('cantidadInput').value = cantidadActual;
    } else {
        // Cambiar a modo FARDOS (OFF)
        modoVenta = 'fardos';
        btnModo.textContent = 'UNIDAD'; // Texto fijo
        btnModo.classList.remove('unidades'); // Fondo blanco
        modoTexto.textContent = 'Fardos';
        
        // Actualizar título con descripción y contenido1 (fardos)
        if (productoSeleccionado && productoTitulo) {
            const descripcion = productoSeleccionado.descripcion || 'PRODUCTO SELECCIONADO';
            const contenido = productoSeleccionado.contenido1 || '-';
            productoTitulo.innerHTML = `<div>${descripcion}</div><div>${contenido}</div>`;
        }
        
        // Mantener la cantidad actual (no recalcular)
        document.getElementById('cantidadInput').value = cantidadActual;
    }
    
    // Recalcular precio y total
     actualizarPrecioSegunTipo(tipoPrecionActual);
 }

function calcularPrecioUnitario(tipo, cantidadUnidades) {
    if (!productoSeleccionado) return 0;
    
    const uMinimaMAyoreo = productoSeleccionado.uminimamayoreo || 1;
    
    let precioBase = 0;
    
    if (modoVenta === 'fardos') {
        // Modo fardos: siempre usar precio mayoreo unitario
        switch(tipo) {
            case 'especial':
                precioBase = productoSeleccionado.precioespecial || 0;
                break;
            case 'mayoreo':
                precioBase = productoSeleccionado.preciomayoreo || 0;
                break;
            case 'detalle':
                precioBase = productoSeleccionado.preciodetalle || 0;
                break;
            default:
                precioBase = productoSeleccionado.precioespecial || 0;
        }
        return precioBase / obtenerUnidadesPorFardo(productoSeleccionado);
    } else {
        // Modo unidades: aplicar lógica de umbral
        if (cantidadUnidades >= uMinimaMAyoreo) {
            // Usar precio mayoreo unitario
            switch(tipo) {
                case 'especial':
                    precioBase = productoSeleccionado.precioespecial || 0;
                    break;
                case 'mayoreo':
                    precioBase = productoSeleccionado.preciomayoreo || 0;
                    break;
                case 'detalle':
                    precioBase = productoSeleccionado.preciodetalle || 0;
                    break;
                default:
                    precioBase = productoSeleccionado.precioespecial || 0;
            }
            return precioBase / obtenerUnidadesPorFardo(productoSeleccionado);
        } else {
            // Usar precio detalle unitario
            const precioDetalle = productoSeleccionado.preciodetalle || 0;
            return precioDetalle / obtenerUnidadesPorFardo(productoSeleccionado);
        }
    }
}

function actualizarPrecioSegunTipo(tipo) {
    if (!productoSeleccionado) return;
    
    const cantidad = parseInt(document.getElementById('cantidadInput').value) || 1;
    let cantidadEnUnidades;
    
    if (modoVenta === 'fardos') {
        cantidadEnUnidades = cantidad * (productoSeleccionado.unidades || 1);
    } else {
        cantidadEnUnidades = cantidad;
    }
    
    const precioUnitario = calcularPrecioUnitario(tipo, cantidadEnUnidades);
    
    document.getElementById('precioActual').textContent = precioUnitario.toFixed(3);
    calcularTotal();
}

function validarYCalcularTotal() {
    if (!productoSeleccionado) return;
    
    const cantidad = parseInt(document.getElementById('cantidadInput').value) || 1;
    
    // Validar cantidad mínima
    if (cantidad < 1) {
        document.getElementById('cantidadInput').value = 1;
        return;
    }
    
    // Recalcular precio según el nuevo umbral
    actualizarPrecioSegunTipo(tipoPrecionActual);
}

function calcularTotal() {
    const cantidad = parseInt(document.getElementById('cantidadInput').value) || 0;
    const precioUnitario = parseFloat(document.getElementById('precioActual').textContent) || 0;
    
    // Calcular CantidadEnUnidadesEfectivas según el modo
    let cantidadEnUnidadesEfectivas;
    if (modoVenta === 'fardos') {
        cantidadEnUnidadesEfectivas = cantidad * obtenerUnidadesPorFardo(productoSeleccionado);
    } else {
        cantidadEnUnidadesEfectivas = cantidad;
    }
    
    // Total = PrecioUnitario * CantidadEnUnidadesEfectivas
    const total = precioUnitario * cantidadEnUnidadesEfectivas;
    
    document.getElementById('totalModal').textContent = total.toFixed(2);
}

function cambiarCantidad(incremento) {
    const input = document.getElementById('cantidadInput');
    const valorActual = parseInt(input.value) || 1;
    const nuevoValor = valorActual + incremento;
    
    if (nuevoValor >= 1) {
        input.value = nuevoValor;
        validarYCalcularTotal();
    }
}

function cerrarModalAgregar() {
    console.log('=== CERRANDO MODAL AGREGAR ===');
    const modal = document.getElementById('agregarProductoModal');
    if (modal) {
        modal.classList.remove('show');
        console.log('Modal cerrado correctamente');
    } else {
        console.log('ERROR: Modal no encontrado');
    }
    productoSeleccionado = null;
    window.editandoProductoId = null;
    console.log('=== MODAL CERRADO ===');
}

async function confirmarAgregarProducto() {
    console.log('=== CONFIRMANDO AGREGAR PRODUCTO ===');
    try {
        await agregarProductoAlPedido();
        console.log('Producto agregado correctamente');
    } catch (error) {
        console.error('Error al agregar producto:', error);
        alert('Error al agregar el producto');
    }
    console.log('=== FIN CONFIRMAR AGREGAR ===');
 }

// Función auxiliar para determinar si un producto es fardo o unidad
function esProductoFardo(producto) {
    // Si tenemos el valor TV de la base de datos, usarlo como fuente de verdad
    if (producto.tv !== undefined && producto.tv !== null) {
        // TV = 'U' significa unidad, TV = '' o vacío significa fardo
        return producto.tv === '' || producto.tv === null;
    }
    
    // Lógica de respaldo para casos donde TV no esté disponible
    return (producto.cantidad % obtenerUnidadesPorFardo(producto) === 0) && (producto.cantidad >= obtenerUnidadesPorFardo(producto));
}

// Función para validar duplicados de productos
function validarProductoDuplicado(codigoProd, modoVentaActual) {
    const productosExistentes = productos.filter(p => p.codigoProd === codigoProd);
    
    if (productosExistentes.length === 0) {
        return { permitir: true }; // No existe, se puede agregar
    }
    
    if (productosExistentes.length >= 2) {
        return { permitir: false, mensaje: 'Este producto ya fue agregado como fardo y como unidad' };
    }
    
    const productoExistente = productosExistentes[0];
    const existeComoFardo = esProductoFardo(productoExistente);
    const agregandoComoFardo = modoVentaActual === 'fardos';
    
    if (existeComoFardo && agregandoComoFardo) {
        return { permitir: false, mensaje: 'Este producto ya fue agregado como fardo' };
    }
    
    if (!existeComoFardo && !agregandoComoFardo) {
        return { permitir: false, mensaje: 'Este producto ya fue agregado como unidad' };
    }
    
    return { permitir: true }; // Existe en modo opuesto, se puede agregar
}

async function agregarProductoAlPedido() {
    if (!productoSeleccionado) return;
    
    const cantidad = parseInt(document.getElementById('cantidadInput').value);
    const precioUnitario = parseFloat(document.getElementById('precioActual').textContent) || 0;
    const tipoPrecionSeleccionado = document.querySelector('.precio-btn.precio-btn-active')?.dataset.tipo || 'especial';
    
    if (cantidad <= 0) {
        alert('La cantidad debe ser mayor a 0');
        return;
    }
    
    // Validar duplicados antes de agregar
    const validacion = validarProductoDuplicado(productoSeleccionado.CodigoProd, modoVenta);
    if (!validacion.permitir) {
        alert(validacion.mensaje);
        return;
    }
    
    // NUEVA VALIDACIÓN DE EXISTENCIAS
    const stockDisponible = productoSeleccionado.existencia || 0;
    
    // Calcular cantidad solicitada en unidades individuales
    let cantidadSolicitadaEnUnidades;
    if (modoVenta === 'fardos') {
        cantidadSolicitadaEnUnidades = cantidad * obtenerUnidadesPorFardo(productoSeleccionado);
    } else {
        cantidadSolicitadaEnUnidades = cantidad;
    }
    
    // Verificar si hay suficiente stock
    if (cantidadSolicitadaEnUnidades > stockDisponible) {
        const unidadTexto = modoVenta === 'fardos' ? 'fardos' : 'unidades';
        const stockEnModoActual = modoVenta === 'fardos' ? Math.floor(stockDisponible / obtenerUnidadesPorFardo(productoSeleccionado)) : stockDisponible;
        
        mostrarMensajeInventario(
            'error',
            'Stock Insuficiente',
            `No hay suficiente inventario para completar esta operación.\n\n` +
            `Producto: ${productoSeleccionado.descripcion}\n` +
            `Disponible: ${stockEnModoActual} ${unidadTexto}\n` +
            `Solicitado: ${cantidad} ${unidadTexto}\n\n` +
            `Ajuste la cantidad o seleccione otro producto.`
        );
        return;
    }
    
    // Convertir cantidad según el modo de venta
    let cantidadEnUnidadesEfectivas;
    
    if (modoVenta === 'fardos') {
        cantidadEnUnidadesEfectivas = cantidad * obtenerUnidadesPorFardo(productoSeleccionado);
    } else {
        cantidadEnUnidadesEfectivas = cantidad;
    }
    
    // Calcular total usando las unidades efectivas
    const total = cantidadEnUnidadesEfectivas * precioUnitario;
    
    // Agregar nuevo producto (ya validamos que no existe en el mismo modo)
    // TV: 'U' para unidades, '' para fardos
    const tvValue = modoVenta === 'unidades' ? 'U' : '';
    
    const nuevoProducto = {
        id: Date.now() + '_' + (tvValue || 'F'),
        codigoProd: productoSeleccionado.CodigoProd,
        descripcion: productoSeleccionado.descripcion,
        contenido1: productoSeleccionado.contenido1,
        contenido2: productoSeleccionado.contenido2,
        cantidad: cantidad, // Cantidad mostrada en UI
        bonificado: 0,
        precio: precioUnitario,
        descuento: 0,
        importe: total,
        tipoPrecion: tipoPrecionSeleccionado,
        autorizacion: '',
        modoVenta: modoVenta, // Guardar el modo para referencia
        unidades: productoSeleccionado.unidades || 1, // Guardar unidades por fardo
        tv: tvValue // Agregar campo TV para que esProductoFardo funcione inmediatamente
    };
    productos.push(nuevoProducto);
    
    // Llamar API para insertar producto inmediatamente en la base de datos
    // IMPORTANTE: Usar cantidadEnUnidadesEfectivas para la base de datos
    // El inventario se actualiza automáticamente en el backend como operación atómica
    await insertarProductoInmediato({
        codigoSIN: pedidoId,
        codigoProd: productoSeleccionado.CodigoProd,
        cantidad: cantidadEnUnidadesEfectivas, // Cantidad en unidades efectivas
        precioVenta: precioUnitario,
        bonificacion: 0,
        descuento: 0,
        tv: tvValue,
        agregarOferta: 'FALSE',
        observaciones: ''
    });
    
    // Actualizar inventario local para reflejar el cambio
    const stockAnterior = productoSeleccionado.existencia || 0;
    const nuevoStock = stockAnterior - cantidadEnUnidadesEfectivas;
    
    // Actualizar el stock en el producto seleccionado
    productoSeleccionado.existencia = nuevoStock;
    
    // Actualizar el stock en la lista de productos disponibles
    const productoEnLista = productosDisponibles.find(p => p.CodigoProd === productoSeleccionado.CodigoProd);
    if (productoEnLista) {
        productoEnLista.existencia = nuevoStock;
    }
    
    // Actualizar la visualización si el modal está abierto
    const modal = document.getElementById('productosModal');
    if (modal && modal.style.display === 'flex') {
        mostrarProductos(productosDisponibles);
    }
    
    actualizarListaProductos();
    actualizarTotal();
    cerrarModalAgregar();
}

function cancelarAgregarProducto() {
    cerrarModalAgregar();
}

// Función para mostrar mensajes informativos del sistema de inventario
function mostrarMensajeInventario(tipo, titulo, mensaje) {
    console.log('=== MOSTRANDO MODAL DE INVENTARIO ===');
    console.log('Tipo:', tipo, 'Título:', titulo);
    
    // ELIMINAR modal existente si existe (solución radical)
    const modalExistente = document.getElementById('inventoryMessageModal');
    if (modalExistente) {
        console.log('Eliminando modal existente...');
        modalExistente.remove();
    }
    
    // CREAR modal completamente nuevo cada vez
    const modal = document.createElement('div');
    modal.id = 'inventoryMessageModal';
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal-content inventory-message-modal">
            <div class="modal-header">
                <h3 id="inventoryMessageTitle">Mensaje</h3>
                <button class="modal-close" id="inventoryModalCloseBtn">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="inventoryMessageIcon" class="message-icon"></div>
                <div id="inventoryMessageText" class="message-text"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" id="inventoryModalOkBtn">Entendido</button>
            </div>
        </div>
    `;
    
    // Agregar al DOM
    document.body.appendChild(modal);
    console.log('Modal nuevo creado y agregado al DOM');
    
    // Event listeners SIMPLES Y DIRECTOS
    const closeBtn = document.getElementById('inventoryModalCloseBtn');
    const okBtn = document.getElementById('inventoryModalOkBtn');
    
    console.log('Botones encontrados:', { closeBtn: !!closeBtn, okBtn: !!okBtn });
    
    // Event listener para botón X
    if (closeBtn) {
        closeBtn.onclick = function() {
            console.log('Clic en botón X detectado');
            cerrarMensajeInventario();
        };
        console.log('✅ Event listener X configurado');
    }
    
    // Event listener para botón Entendido
    if (okBtn) {
        okBtn.onclick = function() {
            console.log('Clic en botón Entendido detectado');
            cerrarMensajeInventario();
        };
        console.log('✅ Event listener Entendido configurado');
    }
    
    // Configurar el contenido según el tipo
    const titleElement = document.getElementById('inventoryMessageTitle');
    const iconElement = document.getElementById('inventoryMessageIcon');
    const textElement = document.getElementById('inventoryMessageText');
    
    titleElement.textContent = titulo;
    textElement.textContent = mensaje;
    
    // Configurar icono y estilo según el tipo
    iconElement.className = 'message-icon';
    switch (tipo) {
        case 'error':
            iconElement.innerHTML = '<i class="fas fa-exclamation-triangle" style="color: #c62828; font-size: 32px;"></i>';
            titleElement.style.color = '#c62828';
            break;
        case 'warning':
            iconElement.innerHTML = '<i class="fas fa-exclamation-circle" style="color: #ef6c00; font-size: 32px;"></i>';
            titleElement.style.color = '#ef6c00';
            break;
        case 'success':
            iconElement.innerHTML = '<i class="fas fa-check-circle" style="color: #2e7d32; font-size: 32px;"></i>';
            titleElement.style.color = '#2e7d32';
            break;
        case 'info':
        default:
            iconElement.innerHTML = '<i class="fas fa-info-circle" style="color: #1976d2; font-size: 32px;"></i>';
            titleElement.style.color = '#1976d2';
            break;
    }
    
    // Event listener para cerrar con clic fuera del modal
    modal.onclick = function(e) {
        if (e.target === modal) {
            console.log('Clic fuera del modal detectado');
            cerrarMensajeInventario();
        }
    };
    console.log('✅ Event listener clic fuera configurado');
    
    // Event listener para cerrar con ESC
    window.inventoryEscHandler = function(e) {
        if (e.key === 'Escape') {
            console.log('Tecla ESC detectada');
            cerrarMensajeInventario();
        }
    };
    document.addEventListener('keydown', window.inventoryEscHandler);
    console.log('✅ Event listener ESC configurado');
    
    // Mostrar el modal
    modal.style.display = 'flex';
    console.log('✅ Modal mostrado');
    console.log('=== MODAL DE INVENTARIO LISTO ===');
}

function cerrarMensajeInventario() {
    console.log('=== CERRANDO MODAL DE INVENTARIO (SOLUCIÓN RADICAL) ===');
    const modal = document.getElementById('inventoryMessageModal');
    
    if (modal) {
        console.log('Modal encontrado, ELIMINANDO completamente del DOM...');
        
        // ELIMINAR completamente del DOM (no solo ocultar)
        modal.remove();
        console.log('✅ Modal ELIMINADO completamente del DOM');
        
        // Limpiar event listeners de ESC si existen
        if (window.inventoryEscHandler) {
            document.removeEventListener('keydown', window.inventoryEscHandler);
            window.inventoryEscHandler = null;
            console.log('✅ Event listener ESC limpiado');
        }
        
        console.log('✅ Modal cerrado y eliminado exitosamente');
    } else {
        console.log('❌ Modal no encontrado en el DOM');
    }
    console.log('=== FIN CIERRE MODAL ===');
}

// Función para actualizar inventario
async function actualizarInventarioProducto(datos) {
    try {
        console.log('Actualizando inventario:', datos);
        
        const response = await fetch('php/api/pedidos.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'actualizar_inventario',
                codigoProd: datos.codigoProd,
                cantidad: datos.cantidad,
                operacion: datos.operacion
            })
        });
        
        const result = await response.json();
        
        if (!result.success) {
            console.error('Error al actualizar inventario:', result.message);
            mostrarMensajeInventario(
                'error',
                'Error de Inventario',
                `No se pudo actualizar el inventario:\n\n${result.message}\n\nPor favor, intente nuevamente o contacte al administrador.`
            );
            throw new Error(result.message);
        }
        
        console.log('Inventario actualizado exitosamente:', result);
        
        // Actualizar el stock en el producto seleccionado para futuras validaciones
        if (productoSeleccionado && productoSeleccionado.CodigoProd === datos.codigoProd) {
            productoSeleccionado.existencia = result.existenciaNueva;
        }
        
        // Actualizar el stock en la lista de productos disponibles
        const productoEnLista = productosDisponibles.find(p => p.CodigoProd === datos.codigoProd);
        if (productoEnLista) {
            productoEnLista.existencia = result.existenciaNueva;
        }
        
        // Actualizar la visualización de productos si el modal está abierto
        const modal = document.getElementById('productosModal');
        if (modal && modal.style.display === 'flex') {
            mostrarProductos(productosDisponibles);
        }
        
        return result;
        
    } catch (error) {
        console.error('Error de conexión al actualizar inventario:', error);
        throw error;
    }
}

async function agregarOfertaProducto() {
    if (!productoSeleccionado) return;
    
    // Marcar como oferta y agregar al pedido
    const cantidad = parseInt(document.getElementById('productoCantidadModal').value);
    const bonificado = parseInt(document.getElementById('productoBonificado').value) || 0;
    const descuentoPorcentaje = parseFloat(document.getElementById('productoDescuento').value) || 0;
    const precioUnitario = parseFloat(document.getElementById('precioActual').textContent) || 0;
    const autorizacion = document.getElementById('autorizacionProducto').value;
    const tipoPrecionSeleccionado = document.querySelector('.btn-precio-tipo.active')?.dataset.tipo || 'mayoreo';
    
    if (cantidad <= 0) {
        alert('La cantidad debe ser mayor a 0');
        return;
    }
    
    // Validar duplicados antes de agregar oferta
    const validacion = validarProductoDuplicado(productoSeleccionado.CodigoProd, modoVenta);
    if (!validacion.permitir) {
        alert(validacion.mensaje);
        return;
    }
    
    // Convertir cantidad según el modo de venta
    let cantidadEnUnidadesEfectivas;
    
    if (modoVenta === 'fardos') {
        cantidadEnUnidadesEfectivas = cantidad * obtenerUnidadesPorFardo(productoSeleccionado);
    } else {
        cantidadEnUnidadesEfectivas = cantidad;
    }
    
    // Calcular total con descuento especial para oferta usando unidades efectivas
    const subtotal = cantidadEnUnidadesEfectivas * precioUnitario;
    const descuento = subtotal * (descuentoPorcentaje / 100);
    const total = subtotal - descuento;
    
    // TV: 'U' para unidades, '' para fardos
    const tvValue = modoVenta === 'unidades' ? 'U' : '';
    
    const nuevoProducto = {
        id: Date.now() + '_' + (tvValue || 'F'),
        codigoProd: productoSeleccionado.CodigoProd,
        descripcion: `[OFERTA] ${productoSeleccionado.descripcion}`,
        contenido1: productoSeleccionado.contenido1,
        contenido2: productoSeleccionado.contenido2,
        cantidad: cantidad, // Cantidad mostrada en UI
        bonificado: bonificado,
        precio: precioUnitario,
        descuento: descuentoPorcentaje,
        importe: total,
        tipoPrecion: tipoPrecionSeleccionado,
        autorizacion: autorizacion,
        esOferta: true,
        modoVenta: modoVenta, // Guardar el modo para referencia
        unidades: productoSeleccionado.unidades || 1, // Guardar unidades por fardo
        tv: tvValue // Agregar campo TV para que esProductoFardo funcione inmediatamente
    };
    
    productos.push(nuevoProducto);
    
    // Llamar API para insertar producto inmediatamente en la base de datos
    // IMPORTANTE: Usar cantidadEnUnidadesEfectivas para la base de datos
    // El inventario se actualiza automáticamente en el backend como operación atómica
    await insertarProductoInmediato({
        codigoSIN: pedidoId,
        codigoProd: productoSeleccionado.CodigoProd,
        cantidad: cantidadEnUnidadesEfectivas, // Cantidad en unidades efectivas
        precioVenta: precioUnitario,
        bonificacion: bonificado,
        descuento: descuentoPorcentaje,
        tv: tvValue,
        agregarOferta: 'TRUE',
        observaciones: autorizacion
    });
    
    // Actualizar inventario local para reflejar el cambio (incluyendo ofertas)
    const stockAnterior = productoSeleccionado.existencia || 0;
    const nuevoStock = stockAnterior - cantidadEnUnidadesEfectivas;
    
    // Actualizar el stock en el producto seleccionado
    productoSeleccionado.existencia = nuevoStock;
    
    // Actualizar el stock en la lista de productos disponibles
    const productoEnLista = productosDisponibles.find(p => p.CodigoProd === productoSeleccionado.CodigoProd);
    if (productoEnLista) {
        productoEnLista.existencia = nuevoStock;
    }
    
    // Actualizar la visualización si el modal está abierto
    const modal = document.getElementById('productosModal');
    if (modal && modal.style.display === 'flex') {
        mostrarProductos(productosDisponibles);
    }
    
    actualizarListaProductos();
    actualizarTotal();
    cerrarModalAgregar();
}

function modificarAutorizacion() {
    const input = document.getElementById('autorizacionProducto');
    const nuevoValor = prompt('Ingrese la autorización:', input.value);
    if (nuevoValor !== null) {
        input.value = nuevoValor;
    }
}

function actualizarListaProductos() {
    const lista = document.getElementById('productosList');
    lista.innerHTML = productos.map(producto => {
        // Usar la función esProductoFardo que considera el campo TV como fuente de verdad
        const esModoFardos = esProductoFardo(producto);
        
        let cantidadMostrar, sufijo, precioMostrar;
        
        if (esModoFardos) {
            // Modo fardos: producto.cantidad ya está en fardos (lo que ingresó el usuario)
            cantidadMostrar = parseFloat(producto.cantidad.toFixed(2));
            sufijo = ' F';
            // Precio por fardo completo
            precioMostrar = producto.precio * obtenerUnidadesPorFardo(producto);
        } else {
            // Modo unidades: mostrar cantidad en unidades con sufijo 'U'
            cantidadMostrar = producto.cantidad;
            sufijo = ' U';
            // Precio unitario
            precioMostrar = producto.precio;
        }
        
        return `
             <div class="producto-item" data-id="${producto.id}" 
                  ontouchstart="iniciarSwipe(${producto.id}, event)" 
                  ontouchmove="moverSwipe(event)" 
                  ontouchend="finalizarSwipe(${producto.id}, event)">
                 <div class="col-descripcion">${producto.descripcion}${producto.bonificado > 0 ? ` (+${producto.bonificado} bonif.)` : ''}${producto.descuento > 0 ? ` (-${producto.descuento}% desc.)` : ''}</div>
                 <div class="col-cantidad">${cantidadMostrar}${sufijo}</div>
                 <div class="col-precio">$${precioMostrar.toFixed(2)}</div>
                 <div class="col-importe">$${producto.importe.toFixed(2)}</div>
             </div>
         `;
     }).join('');
 }

async function eliminarProducto(id) {
    // Buscar el producto a eliminar
    const producto = productos.find(p => p.id === id);
    if (!producto) {
        console.error('Producto no encontrado:', id);
        return;
    }
    
    // Si estamos en modo edición, eliminar también de la base de datos
    if (window.modoEdicion) {
        try {
            console.log('Eliminando producto de la base de datos:', producto);
            
            const response = await fetch('php/api/pedidos.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'eliminar_producto_pedido',
                    codigoSIN: pedidoId,
                    codigoProd: producto.codigoProd,
                    tv: producto.tv || '' // Incluir campo TV para distinguir fardo/unidad
                })
            });
            
            const result = await response.json();
            
            if (!result.success) {
                console.error('Error al eliminar producto de la base de datos:', result.message);
                alert('Error al eliminar el producto: ' + result.message);
                return;
            }
            
            console.log('Producto eliminado exitosamente de la base de datos');
            
            // Actualizar inventario local si se devolvieron unidades
            if (result.cantidadDevuelta) {
                // Actualizar el stock en el producto seleccionado si coincide
                if (productoSeleccionado && productoSeleccionado.CodigoProd === producto.codigoProd) {
                    productoSeleccionado.existencia = (productoSeleccionado.existencia || 0) + result.cantidadDevuelta;
                }
                
                // Actualizar el stock en la lista de productos disponibles
                const productoEnLista = productosDisponibles.find(p => p.CodigoProd === producto.codigoProd);
                if (productoEnLista) {
                    productoEnLista.existencia = (productoEnLista.existencia || 0) + result.cantidadDevuelta;
                }
                
                console.log('Inventario local actualizado: +' + result.cantidadDevuelta + ' unidades para ' + producto.codigoProd);
            }
            
        } catch (error) {
            console.error('Error de conexión al eliminar producto:', error);
            alert('Error de conexión al eliminar el producto');
            return;
        }
    }
    
    // Eliminar del array local
    productos = productos.filter(p => p.id !== id);
    actualizarListaProductos();
    actualizarTotal();
}

function actualizarTotal() {
    totalPedido = productos.reduce((sum, producto) => sum + (producto.importe || 0), 0);
    document.getElementById('totalPedido').textContent = totalPedido.toFixed(2);
}

// Variables para animación de swipe
let currentSwipeElement = null;

// Funciones para Swipe y Modal de Eliminación
function iniciarSwipe(productoId, event) {
    // Prevenir selección de texto
    event.preventDefault();
    
    // Obtener coordenadas iniciales del touch
    const touch = event.touches[0];
    swipeStartX = touch.clientX;
    swipeStartY = touch.clientY;
    swipeStartTime = Date.now();
    
    // Encontrar el producto
    const producto = productos.find(p => p.id === productoId);
    if (!producto) return;
    
    // Encontrar el elemento DOM del producto
    currentSwipeElement = event.currentTarget;
    
    // Agregar clase de swipe activo
    currentSwipeElement.classList.add('swiping');
    
    // Marcar como activo y guardar referencia
    swipeActive = true;
    productoAEliminar = producto;
}

// Función para cargar productos existentes de un pedido
async function cargarProductosExistentes(codigoSIN) {
    try {
        console.log('=== CARGANDO PRODUCTOS EXISTENTES ===');
        console.log('CodigoSIN:', codigoSIN);
        
        const response = await fetch('php/api/pedidos.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'obtener_productos_pedido',
                codigoSIN: codigoSIN
            })
        });
        
        const result = await response.json();
        console.log('Respuesta de productos existentes:', result);
        
        if (result.success && result.productos) {
            // Limpiar array de productos actual
            productos = [];
            
            // Cargar productos existentes
            result.productos.forEach(producto => {
                productos.push({
                    id: producto.idDetalle, // Usar IDDetalle como identificador único
                    idDetalle: producto.idDetalle, // Mantener referencia al IDDetalle
                    codigoProd: producto.codigoProd,
                    descripcion: producto.descripcion,
                    contenido1: producto.contenido1,
                    contenido2: producto.contenido2,
                    cantidad: producto.cantidad,
                    precio: producto.precio,
                    importe: producto.importe,
                    bonificacion: producto.bonificacion,
                    descuento: producto.descuento,
                    oferta: producto.oferta,
                    autorizacion: producto.autorizacion,
                    unidades: producto.unidades,
                    tipoproducto: producto.tipoproducto,
                    tv: producto.tv // Preservar campo TV de la base de datos
                });
            });
            
            // Actualizar la interfaz
            actualizarListaProductos();
            actualizarTotal();
            
            console.log('✅ Productos cargados exitosamente:', productos.length);
        } else {
            console.log('ℹ️ No hay productos existentes o error:', result.message);
        }
        
    } catch (error) {
        console.error('❌ Error al cargar productos existentes:', error);
    }
    
    console.log('=== FIN CARGA PRODUCTOS EXISTENTES ===');
}