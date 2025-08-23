// JavaScript para rutas.php
// Las variables userId y currentDay se declaran en PHP

// Nombres de los días
const dayNames = {
    1: 'DOMINGO',
    2: 'LUNES', 
    3: 'MARTES',
    4: 'MIERCOLES',
    5: 'JUEVES',
    6: 'VIERNES',
    7: 'SABADO'
};

let allClients = [];
let filteredClients = [];
let currentFilter = 'pendientes';

// Variables globales para el cliente seleccionado
let selectedClient = {};

// Función para inicializar la página
function initializePage() {
    // Configurar el nombre del día
    document.getElementById('dayName').textContent = dayNames[currentDay];
    
    // Cargar clientes
    loadClients();
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', initializePage);

// Event listeners
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        currentFilter = this.dataset.status;
        filterClients();
    });
});

document.getElementById('searchInput').addEventListener('input', function() {
    filterClients();
});

document.getElementById('clearBtn').addEventListener('click', function() {
    document.getElementById('searchInput').value = '';
    filterClients();
});

async function loadClients() {
    try {
        const response = await fetch(`php/api/rutas.php?empleado=${userId}&dia=${currentDay}`);
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
        const matchesSearch = client.Nombre.toLowerCase().includes(searchTerm) || 
                            client.Establecimiento.toLowerCase().includes(searchTerm) ||
                            client.CodigoCli.toLowerCase().includes(searchTerm);
        
        if (currentFilter === 'todos') return matchesSearch;
        if (currentFilter === 'pendientes') return matchesSearch && (!client.UltimoPedido || client.UltimoPedido === '');
        if (currentFilter === 'procesados') return matchesSearch && (client.UltimoPedido && client.UltimoPedido !== '');
        
        return matchesSearch;
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
                    <div class="cliente-name clickable" onclick="openModal('${client.CodigoCli}', '${client.Nombre}', '${client.Establecimiento}')">${client.Nombre} - ${client.Establecimiento}</div>
                    <div class="cliente-details">
                        <div>${client.CodigoCli} - ${client.Direccion}</div>
                        <div>(${client.IDZona}) ${client.IDMunicipio} &nbsp;&nbsp; Ultimo Pedido: ${hasOrder ? client.UltimoPedido : ''} &nbsp;&nbsp; <span class="status-text">${statusText}</span></div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
    
    container.innerHTML = html;
}

// Función para abrir el modal
function openModal(codigoCli, nombre, establecimiento) {
    selectedClient = {
        codigo: codigoCli,
        nombre: nombre,
        establecimiento: establecimiento
    };
    document.getElementById('clientModal').style.display = 'flex';
}

// Función para cerrar el modal
function closeModal() {
    document.getElementById('clientModal').style.display = 'none';
    selectedClient = {};
}

// Cerrar modal al hacer clic fuera de él
document.getElementById('clientModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// Funciones de las opciones del modal (placeholders por ahora)
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
    alert(`Registrar Visita para: ${selectedClient.nombre} - ${selectedClient.establecimiento}`);
    closeModal();
}

function cobrosPendientes() {
    alert(`Cobros Pendientes para: ${selectedClient.nombre} - ${selectedClient.establecimiento}`);
    closeModal();
}

function datosClientes() {
    alert(`Datos del Cliente: ${selectedClient.nombre} - ${selectedClient.establecimiento}`);
    closeModal();
}