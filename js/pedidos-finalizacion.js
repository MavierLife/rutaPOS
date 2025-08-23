// Funciones de finalización de pedidos y utilidades

async function finalizarPedido() {
    if (productos.length === 0) {
        alert('Debe agregar al menos un producto al pedido');
        return;
    }
    
    // Verificar que el pedido inicial fue registrado
    if (!window.pedidoRegistrado) {
        alert('Error: El pedido no fue registrado inicialmente. Recargue la página.');
        return;
    }
    
    // Obtener tipo de pago seleccionado
    const tipoSeleccionado = document.querySelector('.btn-tipo-pago.active');
    if (!tipoSeleccionado) {
        alert('Debe seleccionar un tipo de pago');
        return;
    }
    
    const condicionPago = tipoSeleccionado.dataset.tipo === 'credito' ? 2 : 1;
    const plazo = condicionPago === 2 ? parseInt(document.getElementById('diasCredito').value) || clienteData.plazoEstablecido : 0;
    
    // Validar plazo si es crédito
    if (condicionPago === 2 && plazo > clienteData.plazoEstablecido) {
        alert(`El plazo no puede exceder los ${clienteData.plazoEstablecido} días establecidos para este cliente`);
        return;
    }
    
    const autorizacion = document.getElementById('autorizacion').value.trim();
    const notas = document.getElementById('notas').value.trim();
    
    // Preparar datos para finalizar el pedido
    const finalizarData = {
        action: 'finalizar',
        codigoSIN: pedidoId,
        productos: productos.map(p => ({
            descripcion: p.descripcion,
            cantidad: p.cantidad,
            precio: p.precio,
            codigoProd: p.codigoProd || '',
            id: p.id || 0,
            tv: 1,
            bonificacion: p.bonificado || 0,
            descuento: p.descuento || 0,
            oferta: 'FALSE',
            observaciones: p.autorizacion || ''
        })),
        importeTotal: totalPedido,
        notas: `${autorizacion ? 'Autorización: ' + autorizacion + '. ' : ''}${notas}`
    };
    
    try {
        // Mostrar indicador de carga
        const btnFinalizar = document.querySelector('.btn-finalizar');
        const textoOriginal = btnFinalizar.textContent;
        btnFinalizar.textContent = 'Finalizando...';
        btnFinalizar.disabled = true;
        
        const response = await fetch('php/api/pedidos.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(finalizarData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(`¡Pedido finalizado exitosamente!\n\nCódigo: ${result.codigoSIN}\nTotal: $${result.total.toFixed(2)}\nProductos: ${productos.length}\nHora finalizada: ${result.horaFinalizada}`);
            
            // Limpiar sesión del pedido actual para permitir nuevos pedidos
            await limpiarSesionPedido();
            
            // Redirigir de vuelta
            window.history.back();
        } else {
            throw new Error(result.message || 'Error desconocido');
        }
        
    } catch (error) {
        console.error('Error al finalizar pedido:', error);
        alert('Error al finalizar el pedido: ' + error.message);
        
        // Restaurar botón
        const btnFinalizar = document.querySelector('.btn-finalizar');
        btnFinalizar.textContent = textoOriginal;
        btnFinalizar.disabled = false;
    }
}

function cerrarPedido() {
    console.log('cerrarPedido() llamada');
    alert('Función cerrarPedido ejecutada');
    if (productos.length > 0) {
        if (confirm('¿Está seguro de cerrar? Se perderán los datos del pedido.')) {
            window.history.back();
        }
    } else {
        window.history.back();
    }
}

// Función para insertar producto inmediatamente en la base de datos
async function insertarProductoInmediato(productoData) {
    try {
        console.log('=== INSERTANDO PRODUCTO INMEDIATO ===');
        console.log('Datos del producto:', productoData);
        
        const response = await fetch('php/api/pedidos.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'agregar_producto_inmediato',
                ...productoData
            })
        });
        
        const result = await response.json();
        console.log('Respuesta de inserción:', result);
        
        if (!result.success) {
            console.error('Error al insertar producto:', result.message);
            alert('Error al guardar el producto: ' + result.message);
        }
        
        return result;
        
    } catch (error) {
        console.error('Error en insertarProductoInmediato:', error);
        alert('Error de conexión al guardar el producto');
        return { success: false, message: error.message };
    }
}

// Función para limpiar la sesión del pedido actual
async function limpiarSesionPedido() {
    try {
        const response = await fetch('php/api/pedidos.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'limpiar_sesion'
            })
        });
        
        const result = await response.json();
        console.log('Sesión limpiada:', result);
    } catch (error) {
        console.error('Error al limpiar sesión:', error);
    }
}

function cambiarTipoPago(tipo) {
    // Validar si se puede seleccionar crédito
    if (tipo === 'credito' && !clienteData.creditoAutorizado) {
        alert('Este cliente no tiene crédito autorizado. Solo se permite pago de contado.');
        return;
    }
    
    document.querySelectorAll('.btn-tipo-pago').forEach(btn => {
        btn.classList.remove('active');
    });
    
    document.querySelector(`[data-tipo="${tipo}"]`).classList.add('active');
    
    const diasCredito = document.getElementById('diasCredito');
    if (tipo === 'credito' && clienteData.creditoAutorizado) {
        diasCredito.style.display = 'block';
        // Establecer el valor por defecto al plazo establecido del cliente
        if (clienteData.plazoEstablecido > 0) {
            diasCredito.value = clienteData.plazoEstablecido;
        }
    } else {
        diasCredito.style.display = 'none';
        diasCredito.value = '';
    }
}

function agregarProducto() {
    mostrarAgregarProducto();
}

function buscarCliente() {
    // Función para buscar cliente - implementar según necesidades
    alert('Función de búsqueda de cliente por implementar');
}

// Cerrar modales al hacer clic fuera
window.addEventListener('click', function(event) {
    const productosModal = document.getElementById('productosModal');
    const agregarModal = document.getElementById('agregarProductoModal');
    
    if (event.target === productosModal) {
        cerrarModalProductos();
    }
    if (event.target === agregarModal) {
        cerrarModalAgregar();
    }
});

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

// Registrar pedido inicial al cargar la página
async function registrarPedidoInicial() {
    console.log('=== INICIANDO REGISTRO PEDIDO INICIAL ===');
    console.log('pedidoId:', pedidoId);
    console.log('clienteData:', clienteData);
    
    try {
        const pedidoInicialData = {
            codigoSIN: pedidoId,
            codigoCli: clienteData.codigo,
            tipoDocumento: clienteData.tipoDocumento,
            condicion: 1, // Por defecto contado
            plazo: 0,
            notas: ''
        };
        
        console.log('Datos del pedido inicial:', pedidoInicialData);
        
        const requestData = {
            action: 'crear_inicial',
            ...pedidoInicialData
        };
        
        console.log('Datos de la petición:', requestData);
        console.log('JSON enviado:', JSON.stringify(requestData));
        
        const response = await fetch('php/api/pedidos.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(requestData)
        });
        
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        const result = await response.json();
        console.log('Respuesta del servidor:', result);
        
        if (result.success) {
            console.log('✅ Pedido registrado inicialmente exitosamente:', result);
            // Guardar información del registro inicial
            window.pedidoRegistrado = true;
            
            // Marcar en la sesión que el registro inicial fue completado
            // Esto evitará duplicados en recargas F5
            await fetch('php/api/pedidos.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'marcar_registro_completado'
                })
            });
            
        } else {
            console.error('❌ Error al registrar pedido inicial:', result.message);
            console.error('Detalles del error:', result);
        }
        
    } catch (error) {
        console.error('❌ Excepción al registrar pedido inicial:', error);
        console.error('Stack trace:', error.stack);
    }
    
    console.log('=== FIN REGISTRO PEDIDO INICIAL ===');
}