// JavaScript para clientes.php
// La variable userId se declara en PHP

let allClients = [];
let filteredClients = [];
let selectedClient = null;

// Función para inicializar la página
function initializePage() {
    // Cargar clientes
    loadClients();
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', initializePage);

// Event listeners
document.getElementById('searchInput').addEventListener('input', function() {
    filterClients();
});

document.getElementById('clearBtn').addEventListener('click', function() {
    document.getElementById('searchInput').value = '';
    filterClients();
});

// Cerrar modal al hacer clic fuera
window.addEventListener('click', function(event) {
    const modal = document.getElementById('clientModal');
    if (event.target === modal) {
        closeModal();
    }
});

async function loadClients() {
    try {
        const response = await fetch('php/api/clientes.php');
        const data = await response.json();
        
        if (data.success) {
            allClients = data.clientes;
            filterClients();
        } else {
            document.getElementById('clientesList').innerHTML = '<div class="error">Error al cargar clientes: ' + data.message + '</div>';
        }
    } catch (error) {
        document.getElementById('clientesList').innerHTML = '<div class="error">Error de conexión</div>';
    }
}

function filterClients() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    
    filteredClients = allClients.filter(client => {
        return client.Nombre.toLowerCase().includes(searchTerm) || 
               client.Establecimiento.toLowerCase().includes(searchTerm) ||
               client.CodigoCli.toLowerCase().includes(searchTerm);
    });
    
    displayClients();
}

function displayClients() {
    const container = document.getElementById('clientesList');
    document.getElementById('clientCount').textContent = filteredClients.length;
    
    if (filteredClients.length === 0) {
        container.innerHTML = '<div class="no-results">No se encontraron clientes</div>';
        return;
    }
    
    const html = filteredClients.map(client => {
        const hasOrder = client.UltimoPedido && client.UltimoPedido !== '';
        const statusClass = hasOrder ? 'procesado' : 'pendiente';
        const statusText = hasOrder ? '' : 'No Visitado';
        
        return `
            <div class="cliente-item ${statusClass}">
                <div class="cliente-check">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="cliente-info">
                    <div class="cliente-name">
                        <span class="clickable-name" onclick="openModal('${client.CodigoCli}', '${client.Nombre}', '${client.Establecimiento}')">
                            ${client.Nombre} - ${client.Establecimiento}
                        </span>
                    </div>
                    <div class="cliente-details">
                        <div>${client.CodigoCli} - ${client.Direccion}</div>
                        <div>(${client.IDZona || 'N/A'}) ${client.IDMunicipio || 'N/A'} &nbsp;&nbsp; Ultimo Pedido: &nbsp;&nbsp; <span class="status-text">${statusText}</span></div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
    
    container.innerHTML = html;
}

function openModal(codigo, nombre, establecimiento) {
    selectedClient = {
        codigo: codigo,
        nombre: nombre,
        establecimiento: establecimiento
    };
    document.getElementById('clientModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('clientModal').style.display = 'none';
    selectedClient = null;
}

function realizarPedido() {
    if (selectedClient) {
        const params = new URLSearchParams({
            nuevo: '1',
            codigo: selectedClient.codigo,
            nombre: selectedClient.nombre,
            establecimiento: selectedClient.establecimiento
        });
        window.location.href = 'pedidos.php?' + params.toString();
    }
    closeModal();
}

function registrarVisita() {
    if (selectedClient) {
        alert('Registrar Visita para: ' + selectedClient.nombre + ' - ' + selectedClient.establecimiento);
    }
    closeModal();
}

function cobrosPendientes() {
    if (selectedClient) {
        alert('Cobros Pendientes para: ' + selectedClient.nombre + ' - ' + selectedClient.establecimiento);
    }
    closeModal();
}

function datosClientes() {
    if (selectedClient) {
        alert('Datos del Cliente: ' + selectedClient.nombre + ' - ' + selectedClient.establecimiento);
    }
    closeModal();
}