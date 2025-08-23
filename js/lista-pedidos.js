// JavaScript para lista-pedidos.php
console.log('=== ARCHIVO lista-pedidos.js CARGADO ===');
let pedidos = [];
let pedidosSeleccionados = [];

// Cargar pedidos al iniciar la página
document.addEventListener('DOMContentLoaded', function() {
    cargarPedidos();
});

async function cargarPedidos() {
    try {
        const response = await fetch('php/api/pedidos.php?action=listar');
        const data = await response.json();
        
        if (data.success) {
            pedidos = data.pedidos;
            mostrarPedidos();
            calcularTotales();
        } else {
            document.getElementById('listaPedidos').innerHTML = '<div class="loading">Error al cargar pedidos: ' + data.message + '</div>';
        }
    } catch (error) {
        console.error('Error al cargar pedidos:', error);
        document.getElementById('listaPedidos').innerHTML = '<div class="loading">Error al cargar pedidos</div>';
    }
}

function mostrarPedidos() {
    console.log('=== FUNCIÓN mostrarPedidos EJECUTADA ===');
    const container = document.getElementById('listaPedidos');
    
    if (pedidos.length === 0) {
        container.innerHTML = '<div class="loading">No hay pedidos registrados</div>';
        document.getElementById('contadorRegistros').textContent = '0';
        return;
    }
    
    let html = '';
    pedidos.forEach(pedido => {
        const esCompletado = pedido.OK == 1;
        const claseEstado = esCompletado ? 'completado' : 'en-proceso';
        const montoContado = pedido.Condicion == 1 ? parseFloat(pedido.ImporteTotal || 0).toFixed(2) : '0.00';
        const montoCredito = pedido.Condicion == 2 ? parseFloat(pedido.ImporteTotal || 0).toFixed(2) : '0.00';
        
        html += `
            <div class="pedido-row ${claseEstado}">
                <div class="cliente-info">
                    <div class="cliente-nombre">${pedido.NombreCliente || 'Cliente no especificado'}</div>
                    <div class="cliente-codigo">${pedido.CodigoCli || ''}</div>
                </div>
                <div class="monto-contado">${montoContado}</div>
                <div class="monto-credito">${montoCredito}</div>
                <div class="acciones">
                    <button class="btn-accion btn-editar" onclick="editarPedido('${pedido.CodigoSIN}')" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-accion btn-eliminar" onclick="eliminarPedido('${pedido.CodigoSIN}')" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    document.getElementById('contadorRegistros').textContent = pedidos.length;
    console.log('=== BOTONES CREADOS, HTML INSERTADO ===');
}

function calcularTotales() {
    let totalContado = 0;
    let totalCredito = 0;
    let totalPreventa = 0;
    
    pedidos.forEach(pedido => {
        const importe = parseFloat(pedido.ImporteTotal || 0);
        
        if (pedido.Condicion == 1) {
            totalContado += importe;
        } else if (pedido.Condicion == 2) {
            totalCredito += importe;
        }
        
        totalPreventa += importe;
    });
    
    document.getElementById('totalContado').textContent = totalContado.toFixed(2);
    document.getElementById('totalCredito').textContent = totalCredito.toFixed(2);
    document.getElementById('totalPreventa').textContent = totalPreventa.toFixed(2);
}

function editarPedido(codigoSIN) {
    // Redirigir a la página de edición de pedidos
    window.location.href = `pedidos.php?editar=${codigoSIN}`;
}

async function eliminarPedido(codigoSIN) {
    console.log('FUNCIÓN eliminarPedido EJECUTADA con codigoSIN:', codigoSIN);
    
    // Buscar los datos del pedido para mostrar en el SweetAlert
    const pedido = pedidos.find(p => p.CodigoSIN === codigoSIN);
    let clienteInfo = 'Cliente no encontrado';
    let montoInfo = 'Monto no disponible';
    
    if (pedido) {
        clienteInfo = pedido.NombreCliente || 'Cliente sin nombre';
        // Usar ImporteTotal que ya está calculado correctamente en el API
        montoInfo = `$ ${parseFloat(pedido.ImporteTotal || 0).toFixed(2)}`;
    }
    
    const result = await Swal.fire({
        title: '¿Eliminar Pedido?',
        html: `<div style="text-align: left; margin: 20px 0;">
                <p><strong>Cliente:</strong> ${clienteInfo}</p>
                <p><strong>Monto:</strong> ${montoInfo}</p>
                <p style="color: #d33; margin-top: 15px;"><strong>Esta acción no se puede deshacer.</strong></p>
               </div>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true
    });
    
    console.log('Resultado de SweetAlert:', result);
    
    if (!result.isConfirmed) {
        console.log('Usuario canceló la eliminación');
        return;
    }
    
    console.log('Usuario confirmó eliminación, procediendo...');
        try {
            console.log('Eliminando pedido:', codigoSIN);
            
            const response = await fetch('php/api/pedidos.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'eliminar_pedido',
                    codigoSIN: codigoSIN
                })
            });
            
            const result = await response.json();
            console.log('Resultado del servidor:', result);
            
            if (result.success) {
                console.log('Pedido eliminado exitosamente');
                await Swal.fire({
                    title: '¡Eliminado!',
                    text: 'El pedido ha sido eliminado exitosamente.',
                    icon: 'success',
                    confirmButtonColor: '#28a745',
                    confirmButtonText: 'OK'
                });
                await cargarPedidos(); // Recargar la lista
            } else {
                console.error('Error del servidor:', result.message);
                await Swal.fire({
                    title: 'Error',
                    text: 'Error al eliminar el pedido: ' + result.message,
                    icon: 'error',
                    confirmButtonColor: '#d33',
                    confirmButtonText: 'OK'
                });
            }
            
        } catch (error) {
            console.error('Error al eliminar pedido:', error);
            await Swal.fire({
                title: 'Error de Conexión',
                text: 'Error de conexión al eliminar el pedido',
                icon: 'error',
                confirmButtonColor: '#d33',
                confirmButtonText: 'OK'
            });
        }
}

// Asegurar que la función esté disponible globalmente
window.eliminarPedido = eliminarPedido;

function enviarPedidosSeleccionados() {
    alert('Función de envío en desarrollo');
}

function backupPedidos() {
    alert('Función de backup en desarrollo');
}