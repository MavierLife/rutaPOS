<?php
// Configurar zona horaria de El Salvador
date_default_timezone_set('America/El_Salvador');

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}

// Obtener el día actual (1=Domingo, 2=Lunes, etc.)
$dayOfWeek = date('w') + 1; // PHP usa 0=Domingo, necesitamos 1=Domingo
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HelenSystem Preventa - Rutas</title>
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/rutas.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <header class="header">
        <span>MovilPOS Preventa</span>
    </header>
    <main class="main-container">
        <div class="rutas-container">
            <div class="rutas-header">
                <button class="btn-rutas-active"><i class="fas fa-route"></i> Rutas</button>
            </div>
            
            <div class="rutas-info">
                <span>Rutas del: <strong id="dayName">CARGANDO...</strong></span>
            </div>
            
            <div class="rutas-tabs">
                <button class="tab-btn active" data-status="pendientes">PENDIENTES</button>
                <button class="tab-btn" data-status="procesados">PROCESADOS</button>
                <button class="tab-btn" data-status="todos">TODOS</button>
            </div>
            
            <div class="search-section">
                <input type="text" id="searchInput" placeholder="Buscar cliente...">
                <button id="clearBtn">Limpiar</button>
            </div>
            
            <div class="clientes-list" id="clientesList">
                <div class="loading">Cargando clientes...</div>
            </div>
            
            <div class="registro-count">
                <span>Registro(s): <span id="clientCount">0</span></span>
            </div>
            
            <button class="btn-cerrar" onclick="window.location.href='dashboard.php'">Cerrar</button>
        </div>
    </main>
    
    <!-- Modal de Opciones de Visita -->
    <div id="clientModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Opciones Visita</h3>
            </div>
            <div class="modal-body">
                <div class="modal-option" onclick="realizarPedido()">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Realizar Pedido</span>
                </div>
                <div class="modal-option" onclick="registrarVisita()">
                    <i class="fas fa-briefcase"></i>
                    <span>Registrar Visita</span>
                </div>
                <div class="modal-option" onclick="cobrosPendientes()">
                    <i class="fas fa-coins"></i>
                    <span>Cobros Pendientes</span>
                </div>
                <div class="modal-option" onclick="datosClientes()">
                    <i class="fas fa-user"></i>
                    <span>Datos Clientes</span>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-modal-cerrar" onclick="closeModal()">Cerrar</button>
            </div>
        </div>
    </div>
    
    <script>
        const userId = <?php echo $_SESSION['user_id']; ?>;
        const currentDay = <?php echo $dayOfWeek; ?>;
    </script>
    <script src="js/rutas.js"></script>
</body>
</html>