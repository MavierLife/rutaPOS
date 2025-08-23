// JavaScript para lista-pedidos.php
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

function eliminarPedido(codigoSIN) {
    if (confirm('¿Está seguro de que desea eliminar este pedido?')) {
        // Implementar eliminación
        console.log('Eliminar pedido:', codigoSIN);
    }
}

function enviarPedidosSeleccionados() {
    alert('Función de envío en desarrollo');
}

function backupPedidos() {
    alert('Función de backup en desarrollo');
}